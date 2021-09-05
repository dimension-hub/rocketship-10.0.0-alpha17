<?php

namespace Drupal\layout_builder;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for reacting to entity events related to Inline Blocks.
 *
 * @internal
 *   This is an internal utility class wrapping hook implementations.
 */
class InlineBlockEntityOperations implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * Inline block usage tracking service.
   *
   * @var \Drupal\layout_builder\InlineBlockUsageInterface
   */
  protected $usage;

  /**
   * The block content storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContentStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
  */
  protected $blockManager;

  /**
   * Constructs a new EntityOperations object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface $usage
   *   Inline block usage tracking service.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Block\BlockManagerInterface|null $block_manager
   *   (optional) The block manager;
   *
   * @todo This constructor has one optional parameter, $block_manager. Deprecate the current
   *    constructor signature in https://www.drupal.org/node/3031492 after the
   *    general policy for constructor backwards compatibility is determined in
   *    https://www.drupal.org/node/3030640.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, InlineBlockUsageInterface $usage, SectionStorageManagerInterface $section_storage_manager, BlockManagerInterface $block_manager = NULL) {
    $this->entityTypeManager = $entityTypeManager;
    $this->blockContentStorage = $entityTypeManager->getStorage('block_content');
    $this->usage = $usage;
    $this->sectionStorageManager = $section_storage_manager;
    if ($block_manager === NULL) {
      @trigger_error('The plugin.manager.block service must be passed to \Drupal\layout_builder\InlineBlockEntityOperations::__construct(). It was added in Drupal 9.1.0 and will be required before Drupal 10.0.0.', E_USER_DEPRECATED);
      $block_manager = \Drupal::service('plugin.manager.block');
    }
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('inline_block.usage'),
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Remove all unused inline blocks on save.
   *
   * Entities that were used in prevision revisions will be removed if not
   * saving a new revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   */
  protected function removeUnusedForEntityOnSave(EntityInterface $entity) {
    // If the entity is new or '$entity->original' is not set then there will
    // not be any unused inline blocks to remove.
    // If this is a revisionable entity then do not remove inline blocks. They
    // could be referenced in previous revisions even if this is not a new
    // revision.
    if ($entity->isNew() || !isset($entity->original) || $entity instanceof RevisionableInterface) {
      return;
    }
    // If the original entity used the default storage then we cannot remove
    // unused inline blocks because they will still be referenced in the
    // defaults.
    if ($this->originalEntityUsesDefaultStorage($entity)) {
      return;
    }

    // Delete and remove the usage for inline blocks that were removed.
    if ($removed_block_ids = $this->getRemovedBlockIds($entity)) {
      $this->deleteBlocksAndUsage($removed_block_ids);
    }
  }

  /**
   * Gets the IDs of the inline blocks that were removed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout entity.
   *
   * @return int[]
   *   The block content IDs that were removed.
   */
  protected function getRemovedBlockIds(EntityInterface $entity) {
    $original_sections = $this->getEntitySections($entity->original);
    $current_sections = $this->getEntitySections($entity);
    // Avoid un-needed conversion from revision IDs to block content IDs by
    // first determining if there are any revisions in the original that are not
    // also in the current sections.
    $current_block_content_revision_ids = $this->getInlineBlockRevisionIdsInSections($current_sections);
    $original_block_content_revision_ids = $this->getInlineBlockRevisionIdsInSections($original_sections);
    if ($unused_original_revision_ids = array_diff($original_block_content_revision_ids, $current_block_content_revision_ids)) {
      // If there are any revisions in the original that aren't in the current
      // there may some blocks that need to be removed.
      $current_block_content_ids = $this->getBlockIdsForRevisionIds($current_block_content_revision_ids);
      $unused_original_block_content_ids = $this->getBlockIdsForRevisionIds($unused_original_revision_ids);
      return array_diff($unused_original_block_content_ids, $current_block_content_ids);
    }
    return [];
  }

  /**
   * Handles entity tracking on deleting a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   */
  public function handleEntityDelete(EntityInterface $entity) {
    // @todo In https://www.drupal.org/node/3008943 call
    //   \Drupal\layout_builder\LayoutEntityHelperTrait::isLayoutCompatibleEntity().
    $this->usage->removeByLayoutEntity($entity);
  }

  /**
   * Handles saving a parent entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   */
  public function handlePreSave(EntityInterface $entity) {
    if (!$this->isLayoutCompatibleEntity($entity)) {
      return;
    }
    $duplicate_blocks = FALSE;

    if ($sections = $this->getEntitySections($entity)) {
      if ($this->originalEntityUsesDefaultStorage($entity)) {
        // This is a new override from a default and the blocks need to be
        // duplicated.
        $duplicate_blocks = TRUE;
      }
      // Since multiple parent entity revisions may reference common block
      // revisions, when a block is modified, it must always result in the
      // creation of a new block revision.
      $new_revision = $entity instanceof RevisionableInterface;
      $section_storage = $this->getSectionStorageForEntity($entity);
      foreach ($this->getInlineBlockComponents($sections) as $component) {
        if (static::isTranslation($section_storage)) {
          $translated_component_configuration = $section_storage->getTranslatedComponentConfiguration($component->getUuid());
          if (isset($translated_component_configuration['block_serialized'])) {
            $this->saveTranslatedInlineBlock($entity, $component->getUuid(), $translated_component_configuration, $new_revision);
          }
        }
        else {
          $this->saveInlineBlockComponent($entity, $component, $new_revision, $duplicate_blocks);
        }
      }
    }
    $this->removeUnusedForEntityOnSave($entity);
  }

  /**
   * Gets a block ID for an inline block plugin.
   *
   * @param \Drupal\layout_builder\Plugin\Block\InlineBlock $block_plugin
   *   The inline block plugin.
   *
   * @return int
   *   The block content ID or null none available.
   */
  protected function getPluginBlockId(InlineBlock $block_plugin) {
    $configuration = $block_plugin->getConfiguration();
    if (!empty($configuration['block_revision_id'])) {
      $revision_ids = $this->getBlockIdsForRevisionIds([$configuration['block_revision_id']]);
      return array_pop($revision_ids);
    }
    return NULL;
  }

  /**
   * Delete the inline blocks and the usage records.
   *
   * @param int[] $block_content_ids
   *   The block content entity IDs.
   */
  protected function deleteBlocksAndUsage(array $block_content_ids) {
    foreach ($block_content_ids as $block_content_id) {
      if ($block = $this->blockContentStorage->load($block_content_id)) {
        $block->delete();
      }
    }
    $this->usage->deleteUsage($block_content_ids);
  }

  /**
   * Removes unused inline blocks.
   *
   * @param int $limit
   *   The maximum number of inline blocks to remove.
   */
  public function removeUnused($limit = 100) {
    $this->deleteBlocksAndUsage($this->usage->getUnused($limit));
  }

  /**
   * Gets blocks IDs for an array of revision IDs.
   *
   * @param int[] $revision_ids
   *   The revision IDs.
   *
   * @return int[]
   *   The block IDs.
   */
  protected function getBlockIdsForRevisionIds(array $revision_ids) {
    if ($revision_ids) {
      $query = $this->blockContentStorage->getQuery()->accessCheck(FALSE);
      $query->condition('revision_id', $revision_ids, 'IN');
      $block_ids = $query->execute();
      return $block_ids;
    }
    return [];
  }

  /**
   * Saves an inline block component.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity with the layout.
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component with an inline block.
   * @param bool $new_revision
   *   Whether a new revision of the block should be created when modified.
   * @param bool $duplicate_blocks
   *   Whether the blocks should be duplicated.
   */
  protected function saveInlineBlockComponent(EntityInterface $entity, SectionComponent $component, $new_revision, $duplicate_blocks) {
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
    $plugin = $component->getPlugin();
    $pre_save_configuration = $plugin->getConfiguration();
    $plugin->saveBlockContent($new_revision, $duplicate_blocks);
    $post_save_configuration = $plugin->getConfiguration();
    if ($duplicate_blocks || (empty($pre_save_configuration['block_revision_id']) && !empty($post_save_configuration['block_revision_id']))) {
      $this->usage->addUsage($this->getPluginBlockId($plugin), $entity);
    }
    $component->setConfiguration($post_save_configuration);
  }

  /**
   * Saves a translated inline block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity with the layout.
   * @param string $component_uuid
   *   The component UUID.
   * @param array $translated_component_configuration
   *   The translated component configuration.
   * @param bool $new_revision
   *   Whether a new revision of the block should be created.
   */
  protected function saveTranslatedInlineBlock(EntityInterface $entity, $component_uuid, array $translated_component_configuration, $new_revision) {
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = unserialize($translated_component_configuration['block_serialized']);
    // Create a InlineBlock plugin from the translated configuration in order to
    // save the block.
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
    $plugin = $this->blockManager->createInstance('inline_block:' . $block->bundle(), $translated_component_configuration);
    $plugin->saveBlockContent($new_revision);
    // Remove serialized block after the block has been saved.
    unset($translated_component_configuration['block_serialized']);

    // Update the block_revision_id in the translated configuration which may
    // have changed after saving the block.
    $configuration = $plugin->getConfiguration();
    $translated_component_configuration['block_revision_id'] = $configuration['block_revision_id'];

    /** @var \Drupal\layout_builder\TranslatableSectionStorageInterface $section_storage */
    $section_storage = $this->getSectionStorageForEntity($entity);
    $section_storage->setTranslatedComponentConfiguration($component_uuid, $translated_component_configuration);
  }

}
