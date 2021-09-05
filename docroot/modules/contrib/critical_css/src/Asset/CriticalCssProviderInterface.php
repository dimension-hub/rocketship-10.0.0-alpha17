<?php

namespace Drupal\critical_css\Asset;

/**
 * Defines an interface for a Critical CSS Provider.
 *
 * Classes implementing this interface calculate which CSS file must be used for
 * Critical CSS and return its contents.
 */
interface CriticalCssProviderInterface {

  /**
   * Get critical CSS contents.
   *
   * @return string
   *   The critical CSS contents
   */
  public function getCriticalCss();

  /**
   * Get all possible paths to search, relatives to theme.
   *
   * @return array
   *   Array with all possible paths.
   */
  public function getFilePaths();

  /**
   * Get matched file path.
   *
   * @return string|null
   *   Matched file path, or null if nothing found.
   */
  public function getMatchedFilePath();

  /**
   * Check if module is enabled.
   *
   * @return bool
   *   True if this module is enabled
   */
  public function isEnabled();

  /**
   * Tells whether this request has been already processed.
   *
   * @return bool
   *   True if already processed, false otherwise.
   */
  public function isAlreadyProcessed();

  /**
   * Reset provider so calculations are made again.
   */
  public function reset();

}
