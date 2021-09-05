<?php

namespace Drupal\layout_builder\ConfigTranslation;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\TranslatableSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update language overrides when components move to different sections.
 *
 * If the language overrides are not updated so that translate component
 * configuration is nested under the new section then override data will be
 * filtered out and the override may be deleted.
 *
 * @see \Drupal\language\Config\LanguageConfigFactoryOverride::onConfigSave()
 *
 * @todo Right now this is called on presave but could also be an eventSubscriber
 *    that runs before
 *    \Drupal\language\Config\LanguageConfigFactoryOverride::onConfigSave().
 */
class LayoutEntityDisplayUpdater implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * LayoutEntityDisplayUpdater constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    // The overrides can only be update if the language manager is configurable.
    if ($language_manager instanceof ConfigurableLanguageManagerInterface) {
      $this->languageManager = $language_manager;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * Updates language overrides if any components have moved to new sections.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display entity.
   */
  public function presaveUpdateOverrides(EntityViewDisplayInterface $display) {
    if (empty($this->languageManager) || !isset($display->original)) {
      return;
    }
    if ($display instanceof LayoutEntityDisplayInterface) {
      if ($display->isLayoutBuilderEnabled() && $display->original->isLayoutBuilderEnabled()) {
        if ($moved_uuids = $this->componentsInNewSections($display)) {
          $storage = $this->getSectionStorageForEntity($display);
          if ($storage instanceof TranslatableSectionStorageInterface) {
            foreach ($this->languageManager->getLanguages() as $language) {
              if ($override = $this->languageManager->getLanguageConfigOverride($language->getId(), $display->getConfigDependencyName())) {
                if ($override->isNew()) {
                  continue;
                }
                $storage->setContext('language', new Context(new ContextDefinition('language', 'language'), $language));
                foreach ($moved_uuids as $moved_uuid) {
                  if ($config = $storage->getTranslatedComponentConfiguration($moved_uuid)) {
                    $storage->setTranslatedComponentConfiguration($moved_uuid, $config);
                    $storage->save();
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * Gets the uuids for any components that have been moved to new section.
   *
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display
   *   The display entity.
   *
   * @return string[]
   *   The uuids.
   */
  private function componentsInNewSections(LayoutEntityDisplayInterface $display) {
    $moved_uuids = [];
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $original_display */
    $original_display = $display->original;
    $original_sections = $original_display->getSections();
    $all_original_uuids = [];

    array_walk($original_sections, function (Section $section) use (&$all_original_uuids) {
      $all_original_uuids = array_merge($all_original_uuids, array_keys($section->getComponents()));
    });
    foreach ($display->getSections() as $delta => $section) {
      $original_section_uuids = isset($original_sections[$delta]) ? array_keys($original_sections[$delta]->getComponents()) : [];
      foreach (array_keys($section->getComponents()) as $uuid) {
        if (!in_array($uuid, $original_section_uuids) && in_array($uuid, $all_original_uuids)) {
          $moved_uuids[] = $uuid;
        }
      }
    }
    return $moved_uuids;
  }

}
