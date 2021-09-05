<?php

namespace Drupal\manage_display_fix_title\Entity;

use Drupal\Core\Routing\EnhancerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Enhances an entity form route with the appropriate controller.
 */
class ManageDisplayRouteEnhancer implements EnhancerInterface {

  const CONTROLLER_REPLACE = [
    '\Drupal\Core\Entity\Controller\EntityViewController::view' => '\Drupal\manage_display_fix_title\Controller\ManageDisplayEntityViewController::view',
    '\Drupal\node\Controller\NodeViewController::view' => '\Drupal\manage_display_fix_title\Controller\ManageDisplayNodeViewController::view',
  ];

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    if (empty(self::CONTROLLER_REPLACE[$defaults['_controller']])) {
      return $defaults;
    }

    $defaults['_controller'] = self::CONTROLLER_REPLACE[$defaults['_controller']];
    return $defaults;
  }

}
