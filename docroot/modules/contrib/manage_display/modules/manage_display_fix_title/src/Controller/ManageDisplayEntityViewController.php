<?php

namespace Drupal\manage_display_fix_title\Controller;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Controller\EntityViewController;

/**
 * Defines a controller to render a single node.
 */
class ManageDisplayEntityViewController extends EntityViewController {

  /**
   * Pre-render callback to build the page title.
   *
   * @param array $page
   *   A page render array.
   *
   * @return array
   *   The changed page render array.
   */
  public function buildTitle(array $page) {
    $entity_type = $page['#entity_type'];
    $entity = $page['#' . $entity_type];
    if ($entity instanceof FieldableEntityInterface) {
      $label_field = $entity->getEntityType()->getKey('label');
      $page_title = [
        '#theme' => 'entity_page_title',
        '#title' => $entity->label(),
        '#entity' => $entity,
        '#view_mode' => $page['#view_mode'],
      ];
      $page['#title'] = $this->renderer->render($page_title);
      unset($page[$label_field]);
    }
    return $page;
  }

}
