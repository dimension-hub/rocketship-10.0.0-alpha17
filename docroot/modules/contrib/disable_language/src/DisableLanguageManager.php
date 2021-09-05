<?php

namespace Drupal\disable_language;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\language\Config\LanguageConfigFactoryOverrideInterface;
use Drupal\language\ConfigurableLanguageManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Language\LanguageManager;

/**
 * Class DisableLanguageManager.
 *
 * @package Drupal\disable_language
 */
class DisableLanguageManager extends ConfigurableLanguageManager {

  /**
   * Contains entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Contains enabled languages.
   *
   * @var enabledLanguages
   */
  protected $enabledLanguages;

  /**
   * Contains disabled languages.
   *
   * @var disabledLanguages
   */
  protected $disabledLanguages;

  /**
   * Contains language_manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * DisableLanguageManager constructor.
   *
   * @param \Drupal\Core\Language\LanguageDefault $default_language
   *   Provides a simple get and set wrapper to the default language object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the interface for a configuration object factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Interface for classes that manage a set of enabled modules.
   * @param \Drupal\language\Config\LanguageConfigFactoryOverrideInterface $config_override
   *   Interface for a configuration factory language override object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack that controls the lifecycle of requests.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Manages entity type plugin definitions.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   Providing language support on language-unaware sites.
   */
  public function __construct(LanguageDefault $default_language, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LanguageConfigFactoryOverrideInterface $config_override, RequestStack $request_stack, EntityTypeManagerInterface $entityTypeManager, languageManager $languageManager) {
    parent::__construct($default_language, $config_factory, $module_handler, $config_override, $request_stack);
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->disabledLanguages = [];
    $this->enabledLanguages = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurableLanguages() {
    $configurableLanguages = [];

    // Get all languages.
    $languages = $this->getLanguages();

    // The language itself doesn't own the thirdPartySetting,
    // So we need to use its matching ConfigEntity
    // Getting the ConfigurableLanguageManager.
    $configManager = $this->entityTypeManager->getStorage('configurable_language');

    $configurableLanguages = $configManager->loadMultiple(array_keys($languages));

    return $configurableLanguages;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisabledLanguages() {

    if (empty($this->disabledLanguages)) {
      foreach ($this->getConfigurableLanguages() as $langcode => $configurableLanguage) {
        $disabled = $configurableLanguage->getThirdPartySetting('disable_language', 'disable');
        if (isset($disabled) && $disabled == 1) {
          $this->disabledLanguages[$langcode] = $configurableLanguage;
        }
      }
    }
    return $this->disabledLanguages;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledLanguages() {

    if (empty($this->enabledLanguages)) {
      foreach ($this->getConfigurableLanguages() as $langcode => $configurableLanguage) {
        $disabled = $configurableLanguage->getThirdPartySetting('disable_language', 'disable');
        if ((isset($disabled) && $disabled == 0) || empty($disabled)) {
          $this->enabledLanguages[$langcode] = $configurableLanguage;
        }

      }
    }
    return $this->enabledLanguages;
  }

  /**
   * {@inheritdoc}
   */
  public function isCurrentLanguageDisabled() {

    $currentLanguageDisabled = FALSE;

    $disabledLangCodes = array_keys($this->getDisabledLanguages());

    if (isset($disabledLangCodes) && in_array($this->languageManager->getCurrentLanguage()
      ->getId(), $disabledLangCodes)
    ) {
      $currentLanguageDisabled = TRUE;
    }

    return $currentLanguageDisabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstEnabledLanguage() {
    $enabledLanguages = $this->getEnabledLanguages();
    $configurableLanguage = reset($enabledLanguages);
    return $this->getLanguage($configurableLanguage->getId());
  }

  /**
   * Determine and return the fallback language id.
   *
   * @return \Drupal\core\Language\LanguageInterface|null
   *   The id of the language that functions as the fallback.
   */
  public function getFallbackLanguage() {

    $redirect_language = FALSE;

    $disabledLangCodes = array_keys($this->getDisabledLanguages());

    if (isset($disabledLangCodes) && in_array($this->languageManager->getCurrentLanguage()
      ->getId(), $disabledLangCodes)
    ) {

      // Get the configurable languages.
      $lang = $this->getConfigurableLanguages();

      $key = $this->languageManager->getCurrentLanguage()->getId();

      if (array_key_exists($this->languageManager->getCurrentLanguage()->getId(), $lang)) {
        $redirect_language = $lang[$key]->getThirdPartySetting('disable_language', 'redirect_language');
      }

    }

    return $redirect_language;

  }

}
