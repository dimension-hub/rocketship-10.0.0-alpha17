<?php

namespace Drupal\lazybuilder\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * @Block(
 *   id = "lazybuilder_lazy_block",
 *   admin_label = @Translation("Lazy block"),
 * )
 */
class LazyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $block['content'] = [
      '#create_placeholder' => TRUE,
      '#lazy_builder' => [
        'lazybuilder.lazy_renderer:renderNodeList', [1000],
      ],
    ];
    return $block;
  }

}
