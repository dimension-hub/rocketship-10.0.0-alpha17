<?php

namespace Drupal\Tests\http_cache_control;

use Drupal\Tests\BrowserTestBase;

/**
 * Enables the page cache and tests it with various HTTP requests.
 *
 * @group http_cache_control
 */
class HttpCacheControlTest extends BrowserTestBase {

  protected $dumpHeaders = TRUE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['test_page_test', 'system_test', 'http_cache_control'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->config('system.site')
      ->set('name', 'Drupal')
      ->set('page.front', '/test-page')
      ->save();
  }

  /**
   * Tests cache headers.
   */
  public function testPageCache() {
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();

    // Fill the cache.
    $this->drupalGet('system-test/set-header', ['query' => ['name' => 'Foo', 'value' => 'max-age']]);

    $this->assertEqual($this->drupalGetHeader('Cache-Control'), 'max-age=300, public', 'Cache-Control header was sent.');
    $this->assertNotContains('s-maxage', $this->drupalGetHeader('Cache-Control'), 'Cache-Control header does not contain s-maxage');
    $this->assertEmpty($this->drupalGetHeader('Surrogate-Control'), 'Surrogate-Control is not present');

    $config = $this->config('http_cache_control.settings');
    $config->set('cache.http.s_maxage', 400);
    $config->save();

    $this->drupalGet('system-test/set-header', ['query' => ['name' => 'Foo', 'value' => 's-maxage']]);
    $this->assertContains('s-maxage=400', $this->drupalGetHeader('Cache-Control'), 'Cache-Control header contain s-maxage');

    $config->set('cache.http.404_max_age', 404);
    $config->save();

    $this->drupalGet('system-test/not-found');
    $this->assertContains('max-age=404', $this->drupalGetHeader('Cache-Control'), 'Cache-Control header contain maxage for 404');
    $this->assertContains('s-maxage=404', $this->drupalGetHeader('Cache-Control'), 'Cache-Control header does not contain s-maxage');

    $config->set('cache.http.vary', 'Drupal-Test-Header');
    $config->save();

    $this->drupalGet('system-test/set-header', ['query' => ['name' => 'Foo', 'value' => 'vary']]);
    $this->assertContains('Drupal-Test-Header', $this->drupalGetHeader('Vary'), 'Vary header contains Drupal-Test-Header.');

    // Surrogate Control tests
    $config->set('cache.surrogate.maxage', 405);
    $config->save();

    $this->drupalGet('system-test/set-header', ['query' => ['name' => 'Foo', 'value' => 'surrogate-max-age']]);
    $this->assertContains('max-age=405', $this->drupalGetHeader('Surrogate-Control'), 'Surrogate-Control header contains maxage');
    $this->assertNotContains('no-store', $this->drupalGetHeader('Surrogate-Control'), 'Surrogate-Control header does not contain no-store');

    $config->set('cache.surrogate.nostore', true);
    $config->save();

    $this->drupalGet('system-test/set-header', ['query' => ['name' => 'Foo', 'value' => 'surrogate-nostore']]);
    $this->assertContains('max-age=405', $this->drupalGetHeader('Surrogate-Control'), 'Surrogate-Control header contains maxage');
    $this->assertContains('no-store', $this->drupalGetHeader('Surrogate-Control'), 'Surrogate-Control header does contain no-store');

  }
}

 ?>
