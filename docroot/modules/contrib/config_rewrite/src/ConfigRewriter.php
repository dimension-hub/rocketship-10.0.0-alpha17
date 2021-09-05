<?php

namespace Drupal\config_rewrite;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\File\FileSystem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\config_rewrite\Exception\NonexistentInitialConfigException;

/**
 * Provides methods to rewrite configuration.
 */
class ConfigRewriter implements ConfigRewriterInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language config factory override service.
   *
   * @var \Drupal\language\Config\LanguageConfigFactoryOverrideInterface|NULL
   */
  protected $languageConfigFactoryOverride;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Constructs a new ConfigRewriter.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   A logger channel.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system.
   * @param \Drupal\language\Config\LanguageConfigFactoryOverrideInterface|NULL $language_config_factory_override
   *   (Optional) The language config factory override service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LoggerChannelInterface $logger, FileSystem $file_system, $language_config_factory_override) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
    $this->languageConfigFactoryOverride = $language_config_factory_override;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Rewrites configuration for a given module.
   *
   * @param $module
   *   The name of a module (without the .module extension).
   */
  public function rewriteModuleConfig($module) {
    // Load the module extension.
    $extension = $this->moduleHandler->getModule($module);

    // Config rewrites are stored in 'modulename/config/rewrite'.
    $dir_base = $extension->getPath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rewrite';
    $languages = \Drupal::languageManager()->getLanguages();

    // Rewrite configuration for the default language.
    $this->rewriteDirectoryConfig($extension, $dir_base);

    // Rewrite configuration for each enabled language.
    foreach ($languages as $langcode => $language) {
      $rewrite_dir = $dir_base . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . $langcode;
      $this->rewriteDirectoryConfig($extension, $rewrite_dir, $langcode);
    }
  }

  /**
   * Finds files in a given directory and uses them to rewrite active config.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension that contains the config rewrites.
   * @param string $rewrite_dir
   *   The directory that contains config rewrites.
   * @param string $langcode
   *   (Optional) The langcode that this configuration is for, if applicable.
   */
  protected function rewriteDirectoryConfig($extension, $rewrite_dir, $langcode = NULL) {
    if ($langcode && !$this->languageConfigFactoryOverride) {
      return;
    }
    // Scan the rewrite directory for rewrites.
    if (file_exists($rewrite_dir) && $files = $this->fileScanDirectory($rewrite_dir, '/^.*\.yml$/i', ['recurse' => FALSE])) {
      foreach ($files as $file) {
        // Parse the rewrites and retrieve the original config.
        $rewrite = Yaml::parse(file_get_contents($rewrite_dir . DIRECTORY_SEPARATOR . $file->name . '.yml'));
        if ($langcode) {
          /** @var \Drupal\language\Config\LanguageConfigOverride $original_config */
          $config = $this->languageConfigFactoryOverride->getOverride($langcode, $file->name);
          $original_data = $config->get();

          $rewrite = $this->rewriteConfig($original_data, $rewrite, $file->name, $extension->getName());
        }
        else {
          $config = $this->configFactory->getEditable($file->name);
          $original_data = $config->getRawData();

          $rewrite = $this->rewriteConfig($original_data, $rewrite, $file->name, $extension->getName());
        }

        // Unset 'config_rewrite' key before saving rewritten values.
        if (isset($rewrite['config_rewrite'])) {
          unset($rewrite['config_rewrite']);
        }

        // Retain the original 'uuid' and '_core' keys if it's not explicitly
        // asked to rewrite them.
        if (isset($rewrite['config_rewrite_uuids'])) {
          unset($rewrite['config_rewrite_uuids']);
        }
        else {
          foreach (['_core', 'uuid'] as $key) {
            if (isset($original_data[$key])) {
              $rewrite[$key] = $original_data[$key];
            }
          }
        }

        // Save the rewritten configuration data.
        $result = $config->setData($rewrite)->save() ? 'rewritten' : 'not rewritten';

        // Log a message indicating whether the config was rewritten or not.
        $log = $langcode ? '@config (@langcode) @result by @module' : '@config @result by @module';
        $this->logger->notice($log, ['@config' => $file->name, '@result' => $result, '@module' => $extension->getName()]);
      }
    }
  }

  /**
   * @param array $original_config
   * @param array $rewrite
   * @param string $config_name
   * @param string $extensionName
   *
   * @return array
   * @throws \Drupal\config_rewrite\Exception\NonexistentInitialConfigException
   */
  public function rewriteConfig($original_config, $rewrite, $config_name, $extensionName) {
    if (empty($original_config)) {
      $message = 'Tried to rewrite config @config via @module module without initial config present.';
      $this->logger->error($message, ['@config' => $config_name, '@module' => $extensionName]);
      throw new NonexistentInitialConfigException("Tried to rewrite config $config_name by $extensionName module without initial config.");
    }

    if (isset($rewrite['config_rewrite']) && $rewrite['config_rewrite'] == 'replace') {
      $rewritten_config = $rewrite;
    } else {
      $rewritten_config = NestedArray::mergeDeep($original_config, $rewrite);
    }
    if (isset($rewrite['config_rewrite']['replace']) && is_array($rewrite['config_rewrite']['replace'])) {
      foreach($rewrite['config_rewrite']['replace'] as $key){
        $parts = explode('.', $key);
        $key_exists = NULL;
        $value = NestedArray::getValue($rewrite, $parts, $key_exists);
        if($key_exists) {
          NestedArray::setValue($rewritten_config, $parts, $value, TRUE);
        } else {
          NestedArray::unsetValue($rewritten_config, $parts);
        }
      }
    }
    return $rewritten_config;
  }

  /**
   * Wraps fileSystem->scanDirectory().
   *
   * @param $dir
   *   The base directory or URI to scan, without trailing slash.
   * @param $mask
   *   The preg_match() regular expression for files to be included.
   * @param $options
   *   An associative array of additional options.
   *
   * @return array
   *   An associative array (keyed on the chosen key) of objects with 'uri',
   *   'filename', and 'name' properties corresponding to the matched files.
   */
  protected function fileScanDirectory($dir, $mask, $options = array()) {
    return $this->fileSystem->scanDirectory($dir, $mask, $options);
  }

}
