<?php

namespace Drupal\Tests\layout_builder_operation_link\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests Layout Builder Operation Link.
 *
 * @group layout_builder_operation_link
 */
class OperationLinkTest extends BrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder_operation_link',
    'node',
    'taxonomy',
  ];

  /**
   * Tests Layout Builder Operation Link.
   */
  public function testOperationLink() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Create user without Layout Builder permissions.
    $auth_user = $this->drupalCreateUser([
      'access administration pages',
      'access content overview',
      'access taxonomy overview',
      'administer taxonomy',
      'bypass node access',
    ]);

    // Create user with Layout Builder permissions.
    $layout_user = $this->drupalCreateUser([
      'access administration pages',
      'access content overview',
      'access taxonomy overview',
      'administer taxonomy',
      'administer node display',
      'administer node fields',
      'administer taxonomy_term display',
      'administer taxonomy_term fields',
      'bypass node access',
      'configure any layout',
    ]);
    $this->drupalLogin($layout_user);

    // Create content types.
    $this->createContentType(['type' => 'page']);
    $this->createContentType(['type' => 'bundle_with_section_field']);

    // Enable Layout Builder w/ overrides for bundle_with_section_field bundle.
    $this->drupalGet("admin/structure/types/manage/bundle_with_section_field/display/default");
    $page->checkField('layout[enabled]');
    $page->pressButton('Save');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    // Create nodes.
    $this->createNode([
      'type' => 'page',
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
    ]);

    // Create taxonomy vocabularies.
    $vocabulary = $this->createVocabulary();
    $vocabulary_with_section_field = $this->createVocabulary();

    // Create terms.
    $this->createTerm($vocabulary);
    $this->createTerm($vocabulary_with_section_field);
    $vocabulary_id = $vocabulary->id();
    $vocabulary_with_section_field_id = $vocabulary_with_section_field->id();

    // Enable Layout Builder w/ overrides for vocabulary_with_section_field_id
    // bundle.
    $this->drupalGet("admin/structure/taxonomy/manage/$vocabulary_with_section_field_id/overview/display");
    $page->checkField('layout[enabled]');
    $page->pressButton('Save');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    // Check for Layout operation link with user with Layout Builder
    // permissions.
    $this->drupalGet('/admin/content');

    $assert_session->elementNotExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "node/1/layout")]');
    $assert_session->elementExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "node/2/layout")]');

    $this->drupalGet("admin/structure/taxonomy/manage/$vocabulary_id/overview/");

    $assert_session->elementNotExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "term/1/layout")]');

    $this->drupalGet("admin/structure/taxonomy/manage/$vocabulary_with_section_field_id/overview/");

    $assert_session->elementExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "term/2/layout")]');

    // Check for Layout operation link with user without Layout Builder
    // permissions.
    $this->drupalLogin($auth_user);

    $this->drupalGet('/admin/content');
    $assert_session->elementNotExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "node/2/layout")]');

    $this->drupalGet("admin/structure/taxonomy/manage/$vocabulary_id/overview/");

    $assert_session->elementNotExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "term/1/layout")]');

    $this->drupalGet("admin/structure/taxonomy/manage/$vocabulary_with_section_field_id/overview/");

    $assert_session->elementNotExists('xpath', '//table//ul[contains(@class, "dropbutton")]//a[contains(@href, "term/2/layout")]');
  }

}
