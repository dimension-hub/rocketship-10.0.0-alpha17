<?php

namespace Drupal\Tests\critical_css\Functional;

use Drupal\Component\Utility\Html;
use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests Critical CSS functionality on HTML HEAD.
 *
 * @group critical_css
 */
class CriticalCssHeadTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'critical_css_test';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'critical_css'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->config('critical_css.settings')->set('dir_path', '/css/critical')->save();
    $this->config('critical_css.settings')->set("enabled", TRUE)->save();
  }

  /**
   * Test critical CSS inside <head> element.
   */
  public function testCriticalCssInHead() {
    // No critical CSS should be found when module is disabled.
    $this->config('critical_css.settings')->set("enabled", FALSE)->save();
    $html = $this->drupalGet("<front>");
    $originalCssFiles = $this->getCssFiles($html);
    $this->assertSession()->responseNotContains('<style id="critical-css">');

    // Critical CSS should be found when module is enabled.
    $this->config('critical_css.settings')->set("enabled", TRUE)->save();
    \Drupal::service("critical_css")->reset();
    $this->drupalGet("/user/login");
    $this->assertSession()->responseContains('<style id="critical-css">/* default-critical.css MOCK */</style>');

    // Homepage's critical CSS should be found when accessing homepage.
    \Drupal::service("critical_css")->reset();
    drupal_flush_all_caches();
    $this->drupalGet("<front>");
    $this->assertSession()->responseContains('<style id="critical-css">/* front.css MOCK */</style>');

    NodeType::create(['type' => 'article'])->save();
    $node = $this->drupalCreateNode(
      [
        'type' => 'article',
        'title' => 'this is an article',
        'status' => 1,
      ]
    );
    $node->save();

    // Node 1's critical CSS should be found when accessing /node/1.
    \Drupal::service("critical_css")->reset();
    $this->drupalGet("/node/1");
    $this->assertSession()->responseContains('<style id="critical-css">/* node-1.css MOCK */</style>');

    // Node 1's critical CSS should be not found when accessing /node/1 and
    // excluded_ids options is empty.
    \Drupal::service("critical_css")->reset();
    drupal_flush_all_caches();
    $this->config('critical_css.settings')->set("excluded_ids", "1\n2")->save();
    $this->drupalGet("/node/1");
    $this->assertSession()->responseNotContains('<style id="critical-css">');

    // No critical CSS should be found when user is logged.
    \Drupal::service("critical_css")->reset();
    $this->drupalLogin($this->rootUser);
    $this->drupalGet("/user/1");
    $this->assertSession()->responseNotContains('<style id="critical-css">');

    // Critical CSS should be found when user is logged and
    // enabled_for_logged_in_users option is enabled.
    \Drupal::service("critical_css")->reset();
    $this->config('critical_css.settings')->set("enabled_for_logged_in_users", TRUE)->save();
    drupal_flush_all_caches();
    $this->drupalGet("/user/1");
    $this->assertSession()->responseContains('<style id="critical-css">/* default-critical.css MOCK */</style>');

    // No non-critical CSS should be preloaded.
    \Drupal::service("critical_css")->reset();
    $html = $this->drupalGet("/user/1");
    $preloadedCssFiles = $this->getCssFiles($html, '//link[@rel="preload" and @as="style"]');
    $this->assertEmpty(array_intersect($originalCssFiles, $preloadedCssFiles), "No non-critical CSS should be preloaded.");

    // Non-critical CSS should be preloaded when preload_non_critical_css
    // options is enabled.
    \Drupal::service("critical_css")->reset();
    $this->config('critical_css.settings')->set("preload_non_critical_css", TRUE)->save();
    $html = $this->drupalGet("/user/1");
    $preloadedCssFiles = $this->getCssFiles($html, '//link[@rel="preload" and @as="style"]');
    $this->assertEqualsArrays($originalCssFiles, $preloadedCssFiles, "Non-critical CSS should be preloaded when preload_non_critical_css options is enabled.");
  }

  /**
   * Get which CSS files are being used in a HTML string.
   *
   * @param string $html
   *   HTML to be parsed.
   * @param string $query
   *   XPath expression to be used.
   *
   * @return array
   *   Array with CSS files
   */
  protected function getCssFiles($html, $query = '//link[@rel="stylesheet"]') {
    $stylesheetLinks = [];
    $document = Html::load($html);
    $xpath = new \DOMXPath($document);
    $dom_nodes = $xpath->query($query);
    foreach ($dom_nodes as $dom_node) {
      $stylesheetLinks[] = $dom_node->getAttribute('href');
    }
    return $stylesheetLinks;
  }

  /**
   * Asserts that two arrays are equal.
   *
   * @param array $expected
   *   Expected array.
   * @param array $actual
   *   Actual array.
   * @param string $message
   *   Message to show on an error.
   */
  protected function assertEqualsArrays(array $expected, array $actual, $message) {
    $this->assertTrue(count($expected) == count(array_intersect($expected, $actual)), $message);
  }

}
