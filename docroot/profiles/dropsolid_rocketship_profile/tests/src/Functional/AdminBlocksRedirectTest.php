<?php

namespace Drupal\Tests\dropsolid_rocketship_profile\Functional;

/**
 * Test the redirect of the Admin block overview page.
 *
 * @group rocketship_blocks
 * @group rocketship
 */
class AdminBlocksRedirectTest extends RocketshipBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rocketship_blocks',
  ];

  /**
   * Tests for a the redirect of the blocks overview page.
   */
  public function testBlocksRedirect() {
    $this->drupalLoginAsWebAdmin();
    $this->drupalGet('/admin/structure/block/block-content');
    $this->assertSession()->addressEquals('/admin/content/blocks');
  }

}
