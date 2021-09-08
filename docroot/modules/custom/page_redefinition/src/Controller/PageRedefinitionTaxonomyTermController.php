<?php

namespace Drupal\page_redefinition\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\TermInterface;

/**
 * Class PageRedefinitionTaxonomyTermController.
 */
class PageRedefinitionTaxonomyTermController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function render(TermInterface $taxonomy_term): ?array {
    if ($taxonomy_term->bundle() === 'categories') {
      return views_embed_view('products', 'embed_1', $taxonomy_term->id());
    }

    // Default term view shipped with drupal core.
    return views_embed_view('taxonomy_term', 'page_1', $taxonomy_term->id());
  }

}
