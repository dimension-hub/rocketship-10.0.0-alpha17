<?php

namespace Drupal\config_import_locale;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the locale.config_subscriber service.
 */
class ConfigImportLocaleServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Changes the class for the locale.config_subscriber to our own class.
    $definition = $container->getDefinition('locale.config_subscriber');
    $definition->setClass('Drupal\config_import_locale\ConfigImportLocaleSubscriber');
  }

}
