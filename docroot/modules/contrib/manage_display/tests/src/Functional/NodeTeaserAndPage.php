<?php

namespace Drupal\Tests\manage_display\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Test the title field is configurable.
 *
 * @group title
 */
class NodeTeaserAndPage extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'manage_display', 'manage_display_fix_title', 'views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
  }

  /**
   * Test the title replacements work as expected.
   */
  public function testNodeTeaserAndPage() {
    // Configure display.
    $display = EntityViewDisplay::load('node.page.default');
    $display->setComponent('uid',
      [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'settings' => ['link' => FALSE],
      ])
      ->save();

    // Create user and node.
    $user = $this->drupalCreateUser(['administer nodes']);
    $this->drupalLogin($user);
    $node = $this->drupalCreateNode(['uid' => $user->id()]);
    $assert = $this->assertSession();

    // Check page display.
    $this->drupalGet($node->toUrl());
    $assert->elementTextContains('css', 'div.field--name-uid', $user->getAccountName());
    $assert->elementNotExists('css', 'div.field--name-uid a');
    $assert->elementTextContains('css', 'h1.page-title span', $node->getTitle());

    // Check teaser display.
    $this->drupalGet('node');
    $assert->elementTextContains('css', 'div.field--name-title h2 a[href="' . $node->toUrl()->toString() . '"]', $node->getTitle());
  }

}
