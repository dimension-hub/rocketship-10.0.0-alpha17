<?php

namespace Drupal\search_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\search_api\Language\StaticLanguageNegotiator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides dynamic services defined by the Search API.
 */
class SearchApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $modules = $container->getParameter('container.modules');

    if (!isset($modules['language'])) {
      return;
    }

    $class = StaticLanguageNegotiator::class;
    $container->register('search_api.static_language_negotiator', $class)
      ->addArgument(new Reference('language_manager'))
      ->addArgument(new Reference('plugin.manager.language_negotiation_method'))
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('settings'))
      ->addArgument(new Reference('request_stack'))
      ->addMethodCall('initLanguageManager');
  }

}
