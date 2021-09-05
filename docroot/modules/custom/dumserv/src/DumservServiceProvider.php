<?php

namespace Drupal\dumserv;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class DumservServiceProvider.
 *
 * @package Drupal\dumserv
 */
class DumservServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $definition = $container->getDefinition('dumserv.random_message');
    $definition->setClass(RandomMessageGenerator::class);
  }

}
