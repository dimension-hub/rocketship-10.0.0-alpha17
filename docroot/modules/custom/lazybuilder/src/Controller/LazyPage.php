<?php

namespace Drupal\lazybuilder\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * {@inheritdoc}
 */
class LazyPage extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $build['#title'] = 'Lazy builder test';
    $build['content'] = [
      '#create_placeholder' => TRUE,
      '#lazy_builder' => [
        'lazybuilder.lazy_renderer:renderNodeList', [3000],
      ],
    ];
    return $build;
  }

}
