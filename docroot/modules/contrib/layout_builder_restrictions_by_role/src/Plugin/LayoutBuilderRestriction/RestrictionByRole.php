<?php

namespace Drupal\layout_builder_restrictions_by_role\Plugin\LayoutBuilderRestriction;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Database\Connection;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionBase;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\user\RoleInterface;
use phpDocumentor\Reflection\Types\Static_;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EntityViewModeRestriction Plugin.
 *
 * @LayoutBuilderRestriction(
 *   id = "restriction_by_role",
 *   title = @Translation("Per Role"),
 *   description = @Translation("Restrict blocks/layouts per role"),
 * )
 */
class RestrictionByRole extends LayoutBuilderRestrictionBase {

  use PluginHelperTrait;

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $class = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $class->currentUser = $container->get('current_user');
    $class->moduleHandler = $container->get('module_handler');
    $class->database = $container->get('database');
    return $class;
  }

  /**
   * {@inheritDoc}
   */
  public function alterBlockDefinitions(array $definitions, array $context) {
    if (!isset($context['delta'])) {
      return $definitions;
    }
    if (!isset($context['section_storage'])) {
      return $definitions;
    }

    $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];

    $third_party_settings = [];
    if ($default instanceof ThirdPartySettingsInterface) {
      $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', []);
    }

    if (empty($third_party_settings['override_defaults'])) {
      // Replace it with defaults
      $third_party_settings = \Drupal::config('layout_builder_restrictions_by_role.settings')->getRawData();
    }

    if (empty($third_party_settings)) {
      // This entity has no restrictions. Look no further.
      return $definitions;
    }

    $layout_id = $context['section_storage']->getSection($context['delta'])
      ->getLayoutId();

    foreach ($definitions as $delta => $definition) {
      if (!$this->isBlockAllowed($delta, $definition, $layout_id, $third_party_settings)) {
        unset($definitions[$delta]);
      }
    }

    return $definitions;
  }

  /**
   * {@inheritDoc}
   */
  public function alterSectionDefinitions(array $definitions, array $context) {
    // Respect restrictions on allowed layouts specified by section storage.
    if (!isset($context['section_storage'])) {
      return $definitions;
    }

    $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
    if ($default instanceof ThirdPartySettingsInterface) {
      $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', []);
      if (empty($third_party_settings['override_defaults'])) {
        // Replace it with defaults
        $third_party_settings = \Drupal::config('layout_builder_restrictions_by_role.settings')->getRawData();
      }
      if (!isset($third_party_settings['layout_restriction']) || $third_party_settings['layout_restriction'] === 'all') {
        // No layout specific restrictions present.
        return $definitions;
      }
      foreach ($this->currentUser->getRoles(TRUE) as $role) {
        if ($role == RoleInterface::ANONYMOUS_ID) {
          continue;
        }
        foreach ($definitions as $layout_id => $definition) {
          if (empty($third_party_settings['allowed_layouts'][$layout_id][$role])) {
            // This layout is not allowed for this role.
            unset($definitions[$layout_id]);
            continue;
          }
        }
      }
    }

    return $definitions;
  }

  /**
   * {@inheritDoc}
   */
  public function blockAllowedinContext(SectionStorageInterface $section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');
    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', []);
    if (empty($third_party_settings['override_defaults'])) {
      // Replace it with defaults
      $third_party_settings = \Drupal::config('layout_builder_restrictions_by_role.settings')->getRawData();
    }
    if (empty($third_party_settings)) {
      // This entity has no restrictions. Look no further.
      return TRUE;
    }

    $bundle = $this->getValuefromSectionStorage([$section_storage], 'bundle');

    // Get "from" section and layout id. (not needed?)
    $section_from = $section_storage->getSection($delta_from);

    // Get "to" section and layout id.
    $section_to = $section_storage->getSection($delta_to);
    $layout_id_to = $section_to->getLayoutId();

    // Get block information.
    $component = $section_from->getComponent($block_uuid)->toArray();
    $block_id = $component['configuration']['id'];

    // Load the plugin definition.
    if ($definition = $this->blockManager()->getDefinition($block_id)) {

      if (!$this->isBlockAllowed($block_id, $definition, $layout_id_to, $third_party_settings)) {
        return $this->t("There is a restriction on %block placement in the %layout %region region for %type content.", [
          "%block" => $definition['admin_label'],
          "%layout" => $layout_id_to,
          "%region" => $region_to,
          "%type" => $bundle,
        ]);
      }
    }

    // Default: this block is not restricted.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region) {
    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');
    $section = $section_storage->getSection($delta);
    $layout_id = $section->getLayoutId();
    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', []);
    if (empty($third_party_settings['override_defaults'])) {
      // Replace it with defaults
      $third_party_settings = \Drupal::config('layout_builder_restrictions_by_role.settings')->getRawData();
    }
    $inline_blocks = $this->getInlineBlockPlugins();
    foreach ($inline_blocks as $key => $block) {
      if (!$this->isBlockAllowed($block, [
        'category' => 'Inline blocks',
        'provider' => '',
      ], $layout_id,
        $third_party_settings)) {
        unset($inline_blocks[$key]);
      }
    }
    return $inline_blocks;
  }

  /**
   * Helper function to retrieve uuid->type keyed block array.
   *
   * @return str[]
   *   A key-value array of uuid-block type.
   */
  private function getBlockTypeByUuid() {
    if ($this->moduleHandler->moduleExists('block_content')) {
      // Pre-load all reusable blocks by UUID to retrieve block type.
      $query = $this->database->select('block_content', 'b')
        ->fields('b', ['uuid', 'type']);
      $results = $query->execute();
      return $results->fetchAllKeyed(0, 1);
    }
    return [];
  }

  /**
   * @param string $block_id
   *   The block ID of the block to check
   * @param array $definition
   *   Array containing block provider and category
   * @param $layout_id
   *   The plugin ID of the layout the block is to be placed in
   * @param $third_party_settings
   *   The settings governing this plugin
   *
   * @return bool
   *   Whether or not this block is allowed to be placed in the given layout
   *   by the current user.
   */
  protected function isBlockAllowed(string $block_id, $definition, $layout_id, $third_party_settings) {
    $content_block_types_by_uuid = $this->getBlockTypeByUuid();

    foreach ($this->currentUser->getRoles(TRUE) as $role) {
      $category = $this->getUntranslatedCategory($definition['category']);
      // Custom blocks get special treatment.
      if ($definition['provider'] == 'block_content') {
        // 'Custom block types' are disregarded if 'Custom blocks'
        // restrictions are enabled.
        $category = 'Custom blocks';
        if (!isset($third_party_settings['__blocks'][$role]['Custom blocks']['restriction_type']) || $third_party_settings['__blocks'][$role]['Custom blocks']['restriction_type'] === 'all') {
          // No custom restrictions for custom blocks so types can be checked.
          $category = 'Custom block types';
          $block_id_exploded = explode(':', $block_id);
          $uuid = $block_id_exploded[1];
          $block_id = $content_block_types_by_uuid[$uuid];
        }
      }

      $restriction_type = $third_party_settings['__blocks'][$role][$category]['restriction_type'] ?? 'all';
      // if all, then continue?
      switch ($restriction_type) {
        case 'all':
          // Not restricted.
          break;
        case 'whitelisted':
          if (empty($third_party_settings['__blocks'][$role][$category]['restrictions'][$block_id])) {
            // Not whitelisted, get rid of it.
            return FALSE;
          }
          break;
        case 'blacklisted':
          if (!empty($third_party_settings['__blocks'][$role][$category]['restrictions'][$block_id])) {
            // Blacklisted, get rid of it.
            return FALSE;
          }
          break;
      }
      // Ok, done checking "all" restriction, if still here check layout specific setting.
      if (!isset($third_party_settings['layout_restriction']) || $third_party_settings['layout_restriction'] === 'all') {
        // No layout specific restrictions present.
        continue;
      }
      if (empty($third_party_settings['allowed_layouts'][$layout_id][$role])) {
        // This layout is not allowed for this role. Hence, no blocks should be available for this layout?
        return FALSE;
      }
      // This layout is allowed, let's see if the current block is allowed for this layout/role combination.
      $restriction_type = $third_party_settings['__layouts'][$layout_id][$role][$category]['restriction_type'] ?? 'all';
      // if all, then continue?
      switch ($restriction_type) {
        case 'all':
          // Not restricted.
          break;
        case 'whitelisted':
          if (empty($third_party_settings['__layouts'][$layout_id][$role][$category]['restrictions'][$block_id])) {
            // Not whitelisted, get rid of it.
            return FALSE;
          }
          break;
        case 'blacklisted':
          if (!empty($third_party_settings['__layouts'][$layout_id][$role][$category]['restrictions'][$block_id])) {
            // Blacklisted, get rid of it.
            return FALSE;
          }
          break;
      }
    }
    return TRUE;
  }

}
