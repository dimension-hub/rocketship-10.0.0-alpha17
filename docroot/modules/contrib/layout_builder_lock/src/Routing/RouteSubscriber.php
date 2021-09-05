<?php

namespace Drupal\layout_builder_lock\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Add additional requirements for Layout builder routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routes = [
      'layout_builder.move_block' => 'block_reorder',
      'layout_builder.move_block_form' => 'block_reorder',
      'layout_builder.choose_block' => 'block_add',
      'layout_builder.choose_inline_block' => 'block_add',
      'layout_builder.update_block' => 'block_config',
      'layout_builder.remove_block' => 'block_remove',
      'layout_builder.choose_section' => 'section_add',
      'layout_builder.configure_section' => 'section_edit',
      'layout_builder.remove_section' => 'section_remove',
    ];

    foreach ($routes as $route_name => $access) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_layout_builder_lock_access', $access);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -100];
    return $events;
  }

}
