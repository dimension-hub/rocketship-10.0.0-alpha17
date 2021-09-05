<?php

namespace Drupal\dropsolid_rocketship_drush\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\SiteProcess;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Parser;
use Webmozart\PathUtil\Path;

/**
 * Class DropsolidRocketshipCommands
 *
 * @package Drupal\dropsolid_rocketship_drush\Commands
 */
class DropsolidRocketshipCommands extends DrushCommands implements ProcessManagerAwareInterface, SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * @var \Drupal\Core\Config\CachedStorage
   */
  private $storage;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * DropsolidRocketshipCommands constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\Config\CachedStorage $storage
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   */
  public function __construct(LanguageManagerInterface $languageManager, CachedStorage $storage, FileSystemInterface $fileSystem) {
    parent::__construct();
    $this->languageManager = $languageManager;
    $this->storage = $storage;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Run first time setup of the configuration management system. It will setup
   * default split config and then export all config splits to the correct
   * folder.
   *
   * @command rocketship:config-setup
   *
   * @usage rocketship:config-setup
   *   Setup config folder
   *
   * @validate-module-enabled dropsolid_rocketship_drush
   *
   * @aliases d-set
   *
   * @throws \Exception
   */
  public function configSetup() {
    // Make sure our settings files have been included. If not, d-set can
    // break config pretty easily if there's no overrides to properly (de)activate
    // config splits.
    if (!defined('ROCKETSHIP_PROJECT_ENVIRONMENT')) {
      throw new \Exception("It seems Rocketship's additional settings file for this environment has not yet been included. \nEnsure the correct additional_settings file in etc/drupal has been included, see the readme file at readme/after-install/readme.md for more information.");
    }

    $default_langcode = $this->languageManager
      ->getDefaultLanguage()
      ->getId();

    // Loop over the install profile's split folders and import that config,
    // then export it to correctly populate the split sync folder. It's a
    // roundabout sorta messy way but it makes sure all the splits are present
    // and have the default stuff in 'em.
    $path = drupal_get_path('profile', 'dropsolid_rocketship_profile') . '/config/splits';
    $directories = glob($path . '/*', GLOB_ONLYDIR);

    // Before we start ex/importing things, we need to make sure the directories
    // exist for *every* split. If this command fails half-way through it can
    // mess up your splits something fierce.
    foreach ($directories as $split) {
      $id = substr($split, strrpos($split, '/') + 1);
      $split_data = $this->storage->read("config_split.config_split.$id");
      if (!$split_data) {
        // Something weird.
        throw new \Exception(t('Could not find configuration for :id split.', [':id' => $id]));
      }
      $split_location = $split_data['folder'];
      if (!$this->fileSystem->prepareDirectory($split_location)) {
        throw new \Exception(t(':folder is not writable.', [':folder' => $split_location]));
      }
    }

    // Standard export to populate sync folder.
    $selfRecord = $this->siteAliasManager()->getSelf();
    /** @var SiteProcess $process */
    $options = ['yes' => TRUE];
    $process = $this->processManager()->drush($selfRecord, 'cex', [], $options);
    $process->mustRun($process->showRealtime());

    foreach ($directories as $split) {
      $this->output()->writeln("Working on $split");

      $id = substr($split, strrpos($split, '/') + 1);
      $source = new FileStorage($split);

      foreach (new \DirectoryIterator($split) as $file) {
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
          $this->storage->write($name, $data);
        }
      }

      // After importing it, export it to its correct split folder.
      $process = $this->processManager()
        ->drush($selfRecord, 'csex', [$id], $options);
      $process->mustRun($process->showRealtime());
    }

    // Import whatever is active.
    $process = $this->processManager()->drush($selfRecord, 'cim', [], $options);
    $process->mustRun($process->showRealtime());
  }


  /**
   * Import a single configuration file.
   *
   * (copied from drupal console, which isn't D9 ready yet)
   *
   * @command rocketship:single-import
   *
   * @usage rocketship:single-import <file>
   *
   * @validate-module-enabled dropsolid_rocketship_drush
   *
   * @aliases rsi
   *
   * @param $file
   *   The path to the file to import
   *
   * @throws \Exception
   */
  public function singleImport($file) {
    if (!$file) {
      throw new \Exception("No file specified.");
    }

    if (!file_exists($file)) {
      throw new \Exception("File not found.");
    }

    $source_storage = new StorageReplaceDataWrapper(
      $this->storage
    );

    $name = Path::getFilenameWithoutExtension($file);
    $ymlFile = new Parser();
    $value = $ymlFile->parse(file_get_contents($file));
    $source_storage->delete($name);
    $source_storage->write($name, $value);

    $storageComparer = new StorageComparer(
      $source_storage,
      $this->storage
    );

    if ($this->configImport($storageComparer)) {
      $this->output()->writeln("Successfully imported $name");
    }
    else {
      throw new \Exception("Failed importing file");
    }
  }

  /**
   * @param \Drupal\Core\Config\StorageComparer $storageComparer
   *
   * @return bool|void
   */
  private function configImport(StorageComparer $storageComparer) {
    $configImporter = new ConfigImporter(
      $storageComparer,
      \Drupal::service('event_dispatcher'),
      \Drupal::service('config.manager'),
      \Drupal::lock(),
      \Drupal::service('config.typed'),
      \Drupal::moduleHandler(),
      \Drupal::service('module_installer'),
      \Drupal::service('theme_handler'),
      \Drupal::service('string_translation'),
      \Drupal::service('extension.list.module')
    );

    if ($configImporter->alreadyImporting()) {
      $this->output()->writeln('Import already running.');
    }
    else {
      if ($configImporter->validate()) {
        $sync_steps = $configImporter->initialize();

        foreach ($sync_steps as $step) {
          $context = [];
          do {
            $configImporter->doSyncStep($step, $context);
          } while ($context['finished'] < 1);
        }
        return TRUE;
      }
    }
  }

}