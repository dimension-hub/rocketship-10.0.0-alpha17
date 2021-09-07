<?php

namespace Drupal\custom_events\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event firing on page and html preprocesses.
 */
class CustomEventsPreprocessEvent extends Event {

  /**
   * Called during hook_preprocess_html().
   */
  public const PREPROCESS_HTML = 'custom_events.frontpage.preprocess_html';

  /**
   * Called during hook_preprocess_page().
   */
  public const PREPROCESS_PAGE = 'custom_events.frontpage.preprocess_page';

  /**
   * Variables from preprocess.
   */
  protected $variables;

  /**
   * DummyFrontpageEvent constructor.
   */
  public function __construct($variables) {
    $this->variables = $variables;
  }

  /**
   * Returns variables array from preprocess.
   */
  public function getVariables() {
    return $this->variables;
  }

}
