<?php

namespace Drupal\dropsolid_rocketship_profile\Plugin\Menu\LocalAction;

use Drupal\block_content\Plugin\Menu\LocalAction\BlockContentAddLocalAction;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Url;

/**
 * Modifies the 'Add custom block' local action.
 */
class RocketshipBlockContentAddLocalAction extends BlockContentAddLocalAction {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);
    // Adds a destination on our own custom block listing under admin/content.
    // Else the redirect goes to instance creation.
    if ($route_match->getRouteName() == 'view.rocketship_content_block_library.page_1') {
      $options['query']['destination'] = Url::fromRoute('<current>')->toString();
    }
    return $options;
  }

}
