<?php

namespace Drupal\plugin\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\plugin\PluginType\PluginTypeManagerInterface;

/**
 * Drush integration for the Plugin module.
 */
class PluginCommands extends DrushCommands {

  /**
   * The Plugin type manager service.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeManagerInterface
   */
  protected $pluginTypeManager;

  /**
   * Creates a PluginCommands instance.
   *
   * @param \Drupal\plugin\PluginType\PluginTypeManagerInterface $plugin_type_manager
   *   The Plugin type manager service.
   */
  public function __construct(
    PluginTypeManagerInterface $plugin_type_manager
  ) {
    $this->pluginTypeManager = $plugin_type_manager;
  }

  /**
   * Adds a cache clear option for plugin types.
   *
   * @hook on-event cache-clear
   */
  public function cacheClear(&$types, $include_bootstrapped_types) {
    if ($include_bootstrapped_types) {
      $types['plugin-types'] = [$this, 'clearPluginTypeCaches'];
    }
  }

  /**
   * Cache clear callback for plugin types.
   *
   * @param $args string[]
   *   An array of plugin type IDs.
   */
  public function clearPluginTypeCaches($args = []) {
    // Get all plugin types if none given as an additional command parameter.
    if (empty($args)) {
      $plugin_types = $this->pluginTypeManager->getPluginTypes();
      $args = array_keys($plugin_types);
    }

    $result = [];
    foreach ($args as $plugin_type_id) {
      // Complain if a plugin type does not exist.
      if (!$this->pluginTypeManager->hasPluginType($plugin_type_id)) {
        throw new \Exception(dt('Plugin type "{type}" does not exist.', [
          'type' => $plugin_type_id,
        ]));
      }

      $plugin_manager = $this->pluginTypeManager->getPluginType($plugin_type_id)->getPluginManager();

      if (!($plugin_manager instanceof CachedDiscoveryInterface)) {
        continue;
      }

      $plugin_manager->clearCachedDefinitions();

      $result[] = dt('Cleared all "{type}" plugin definitions.', [
        'type' => $plugin_type_id,
      ]);
    }

    // Show all the output together, to make it a little more compact.
    if ($result) {
      $this->io()->success($result);
    }
  }

}
