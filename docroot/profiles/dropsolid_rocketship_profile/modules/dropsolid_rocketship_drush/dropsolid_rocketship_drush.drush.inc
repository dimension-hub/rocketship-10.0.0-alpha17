<?php

/**
 * @file
 * Contains the code to generate the custom drush commands.
 */

use Drupal\Core\Config\FileStorage;

/**
 * Implements hook_drush_command().
 */
function dropsolid_rocketship_drush_drush_command() {
  $items = [];
  $items['dropsolid-config-setup'] = [
    'description' => 'Run first time setup of the configuration management system. It will setup default split config and then export all config splits to the correct folder',
    'drupal dependencies' => [
      'dropsolid_rocketship_drush',
      'config_split',
      'dropsolid_rocketship_profile',
    ],
    'aliases' => ['d-set'],
  ];
  return $items;
}

/**
 * Run first time setup of the configuration management system.
 */
function drush_dropsolid_rocketship_drush_dropsolid_config_setup() {
  // Make sure our settings files have been included. If not, d-set can
  // break config pretty easily if there's no overrides to properly (de)activate
  // config splits.
  if (!defined('ROCKETSHIP_PROJECT_ENVIRONMENT')) {
    drush_set_error("It seems Rocketship's additional settings file for this environment has not yet been included. \nEnsure the correct additional_settings file in etc/drupal has been included, see the readme file at readme/after-install/readme.md for more information.");
    return;
  }
  $default_langcode = \Drupal::languageManager()
    ->getDefaultLanguage()
    ->getId();

  // Loop over the install profile's split folders and import that config,
  // then export it to correctly populate the split sync folder. It's a
  // roundabout sorta messy way but it makes sure all the splits are present
  // and have the default stuff in 'em.
  $path = drupal_get_path('profile', 'dropsolid_rocketship_profile') . '/config/splits';
  $directories = glob($path . '/*', GLOB_ONLYDIR);

  /** @var \Drupal\Core\Config\CachedStorage $config_storage */
  $config_storage = \Drupal::service('config.storage');
  /** @var \Drupal\Core\File\FileSystemInterface $file_system */
  $file_system = \Drupal::service('file_system');

  // Before we start ex/importing things, we need to make sure the directories
  // exist for *every* split. If this command fails half-way through it can
  // mess up your splits something fierce.
  foreach ($directories as $split) {
    $id = substr($split, strrpos($split, '/') + 1);
    $split_data = $config_storage->read("config_split.config_split.$id");
    if (!$split_data) {
      // Something weird.
      drush_set_error(t('Could not find configuration for :id split.', [':id' => $id]));
      return;
    }
    $split_location = $split_data['folder'];
    if (!$file_system->prepareDirectory($split_location)) {
      drush_set_error(t(':folder is not writable.', [':folder' => $split_location]));
      return;
    }
  }

  // Standard export to populate sync folder.
  drush_invoke_process('@self', 'cex');

  foreach ($directories as $split) {
    $id = substr($split, strrpos($split, '/') + 1);
    $source = new FileStorage($split);

    foreach (new DirectoryIterator($split) as $file) {
      if ($file->isFile()) {
        $name = $file->getBasename('.yml');
        $data = $source->read($name);
        // @todo: see how to do it properly, using
        // LanguageConfigFactoryOverride and ConfigInstaller
        // for now, just replace langcode to default language. None of these
        // splits really have translatable
        // info anyway
        if (isset($data['langcode'])) {
          $data['langcode'] = $default_langcode;
        }
        $config_storage->write($name, $data);
      }
    }

    // After importing it, export it to its correct split folder.
    drush_invoke_process('@self', 'csex', [$id]);
  }

  // Import whatever is active.
  drush_invoke_process('@self', 'cim');
}
