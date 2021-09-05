<?php

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the addition of the admin theme option for views with page displays.
 *
 * @group views
 * @group legacy
 */
class AlwaysUseAdminThemeOptionUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests views_post_update_always_use_admin_theme().
   *
   * @see views_post_update_always_use_admin_theme()
   */
  public function testViewsPostUpdateEnforceAdminTheme() {
    $options = $this->config('views.view.content')
      ->get('display.page_1.display_options');
    // Check that always_use_admin_theme option doesn't exist in 'content' view.
    $this->assertArrayNotHasKey('always_use_admin_theme', $options);

    // Run updates.
    $this->runUpdates();

    $options = $this->config('views.view.content')
      ->get('display.page_1.display_options');
    // Check that always_use_admin_theme option was added in 'content' view.
    $this->assertArrayHasKey('always_use_admin_theme', $options);
    // Check that always_use_admin_theme option is FALSE.
    $this->assertFalse($options['always_use_admin_theme']);
  }

}
