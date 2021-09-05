<?php

namespace Drupal\lazybuilder;

/**
 * {@inheritdoc}
 */
class LazyRenderer {

  /**
   * Renderer for lazybuilder_node_list theme hook.
   */
  public function renderNodeList($max_nodes = 10): array {
    return [
      '#theme' => 'lazybuilder_node_list',
      '#limit' => $max_nodes,
    ];
  }

}
