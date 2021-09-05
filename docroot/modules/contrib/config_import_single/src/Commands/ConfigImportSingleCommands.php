<?php

namespace Drupal\config_import_single\Commands;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drush\Commands\DrushCommands;
use Exception;
use Symfony\Component\Yaml\Parser;
use Webmozart\PathUtil\Path;

/**
 * Class to import single files into config.
 *
 * @package Drupal\config_import_single\Commands
 */
class ConfigImportSingleCommands extends DrushCommands {

  /**
   * CachedStorage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   *   CachedStorage.
   */
  private $storage;

  /**
   * Event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   *   EventDispatcher.
   */
  private $eventDispatcher;

  /**
   * Config manager.
   *
   * @var \Drupal\Core\Config\ConfigManager
   *   configManager.
   */
  private $configManager;

  /**
   * Lock.
   *
   * @var \Drupal\Core\Lock\DatabaseLockBackend
   *   lock.
   */
  private $lock;

  /**
   * Config typed.
   *
   * @var \Drupal\Core\Config\TypedConfigManager
   *   configTyped.
   */
  private $configTyped;

  /**
   * ModuleHandler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   *   moduleHandler.
   */
  private $moduleHandler;

  /**
   * ModuleInstaller.
   *
   * @var \Drupal\Core\Extension\ModuleInstaller
   *   moduleInstaller.
   */
  private $moduleInstaller;

  /**
   * ThemeHandler.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   *   themeHandler.
   */
  private $themeHandler;

  /**
   * String translation.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   *   stringTranslation.
   */
  private $stringTranslation;

  /**
   * Extension list module.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   *   extensionListModule.
   */
  private $extensionListModule;

  /**
   * ConfigImportSingleCommands constructor.
   *
   * @param \Drupal\Core\Config\CachedStorage $storage
   *   CachedStorage.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   Event Dispatcher.
   * @param \Drupal\Core\Config\ConfigManager $configManager
   *   Config Manager.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Lock.
   * @param \Drupal\Core\Config\TypedConfigManager $configTyped
   *   Config typed.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
   *   Module Installer.
   * @param \Drupal\Core\Extension\ThemeHandler $themeHandler
   *   Theme handler.
   * @param \Drupal\Core\StringTranslation\TranslationManager $stringTranslation
   *   String Translation.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensionListModule
   *   Extension list module.
   */
  public function __construct(CachedStorage $storage, ContainerAwareEventDispatcher	$eventDispatcher, ConfigManager	$configManager, LockBackendInterface $lock, TypedConfigManager $configTyped, ModuleHandler $moduleHandler, ModuleInstallerInterface $moduleInstaller, ThemeHandler $themeHandler, TranslationManager $stringTranslation, ModuleExtensionList $extensionListModule) {
    parent::__construct();
    $this->storage = $storage;
    $this->eventDispatcher = $eventDispatcher;
    $this->configManager = $configManager;
    $this->lock = $lock;
    $this->configTyped = $configTyped;
    $this->moduleHandler = $moduleHandler;
    $this->moduleInstaller = $moduleInstaller;
    $this->themeHandler = $themeHandler;
    $this->stringTranslation = $stringTranslation;
    $this->extensionListModule = $extensionListModule;
  }

  /**
   * Import a single configuration file.
   *
   * (copied from drupal console, which isn't D9 ready yet)
   *
   * @param string $file
   *   The path to the file to import.
   *
   * @command config_import_single:single-import
   *
   * @usage config_import_single:single-import <file>
   *
   * @validate-module-enabled config_import_single
   *
   * @aliases cis
   *
   * @throws \Exception
   */
  public function singleImport(string $file) {
    if (!$file) {
      throw new Exception("No file specified.");
    }

    if (!file_exists($file)) {
      throw new Exception("File not found.");
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
      throw new Exception("Failed importing file");
    }
  }

  /**
   * Import the config.
   *
   * @param \Drupal\Core\Config\StorageComparer $storageComparer
   *   The storage comparer.
   *
   * @return bool|void
   *   Returns TRUE if succeeded.
   */
  private function configImport(StorageComparer $storageComparer) {
    $configImporter = new ConfigImporter(
      $storageComparer,
      $this->eventDispatcher,
      $this->configManager,
      $this->lock,
      $this->configTyped,
      $this->moduleHandler,
      $this->moduleInstaller,
      $this->themeHandler,
      $this->stringTranslation,
      $this->extensionListModule,
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
