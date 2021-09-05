<?php

namespace Drupal\config_import_locale;

use Drupal\locale\LocaleConfigSubscriber;

/**
 * This class extends the LocaleConfigSubscriber in Drupal\locale.
 *
 * The only function that is overwritten is updateLocaleStorage, to make sure it
 * respects the overwrite_interface_translation setting of this module.
 *
 * @see \Drupal\locale\LocaleConfigSubscriber
 */
class ConfigImportLocaleSubscriber extends LocaleConfigSubscriber {

  /**
   * Saves a translation string and marks it as customized.
   *
   * We overwrite this function to make sure it checks the
   * overwrite_interface_translation setting of this module.
   */
  protected function saveCustomizedTranslation($name, $source, $context, $new_translation, $langcode) {
    // Load our config.
    $config_import_locale_config = \Drupal::config('config_import_locale.settings');
    $overwrite = $config_import_locale_config->get('overwrite_interface_translation');

    // Call the correct function, based on our config.
    switch ($overwrite) {
      case 'no_overwrite':
        $this->saveCustomizedTranslationNoOverwrite($name, $source, $context, $new_translation, $langcode);
        break;

      case 'nothing':
        // Do nothing.
        break;

      default:
        parent::saveCustomizedTranslation($name, $source, $context, $new_translation, $langcode);
    }
  }

  /**
   * Updates an interface translation if no previous translation is set.
   *
   * Code is basically the same as
   * LocaleConfigSubscriber::saveCustomizedTranslation, but with a more
   * restrictive if-structure.
   */
  protected function saveCustomizedTranslationNoOverwrite($name, $source, $context, $new_translation, $langcode) {
    $locale_translation = $this->localeConfigManager->getStringTranslation($name, $langcode, $source, $context);
    if (!empty($locale_translation)) {
      $existing_translation = $locale_translation->getString();
      if (($locale_translation->isNew() && $source != $new_translation) ||
        (!$locale_translation->isNew() && empty($existing_translation) && $source != $new_translation)) {
        $locale_translation
          ->setString($new_translation)
          ->setCustomized(TRUE)
          ->save();
      }
    }
  }

}
