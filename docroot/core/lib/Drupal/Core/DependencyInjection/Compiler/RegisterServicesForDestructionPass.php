<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds services tagged "needs_destruction" to the "kernel_destruct_subscriber"
 * service.
 *
 * @see \Drupal\Core\DestructableInterface
 */
class RegisterServicesForDestructionPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    if (!$container->hasDefinition('kernel_destruct_subscriber')) {
      return;
    }

    $definition = $container->getDefinition('kernel_destruct_subscriber');
    $services = $container->findTaggedServiceIds('needs_destruction');

    // Sort by priority. Higher priorities go first.
    uasort($services, function ($a, $b) {
      $a_priority = isset($a[0]['priority']) ? $a[0]['priority'] : 0;
      $b_priority = isset($b[0]['priority']) ? $b[0]['priority'] : 0;
      return $a_priority < $b_priority;
    });

    foreach ($services as $id => $attributes) {
      $definition->addMethodCall('registerService', [$id]);
    }
  }

}
