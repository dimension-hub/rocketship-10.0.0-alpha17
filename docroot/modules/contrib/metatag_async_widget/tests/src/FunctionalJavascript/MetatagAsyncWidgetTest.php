<?php

namespace Drupal\Tests\metatag_async_widget\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Ensures that metatag_async_widget works with JavaScript enabled.
 *
 * @group metatag_async_widget
 */
class MetatagAsyncWidgetTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field_ui',
    'metatag_async_widget',
    'node',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Setup basic environment.
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    // Set up a content type.
    $name = $this->randomMachineName() . ' ' . $this->randomMachineName();
    $this->drupalCreateContentType(['type' => 'metatag_node', 'name' => $name]);

    // Create and login user.
    $this->drupalLogin($this->drupalCreateUser([
      'administer content types',
      'administer nodes',
      'administer node fields',
      'bypass node access',
      'administer node form display',
    ]));
  }

  /**
   * Tests the Metatag Async Widget with Stark.
   */
  public function testMetatagAsyncWidgetStark() {
    $this->doTestMetatagAsyncWidget('//li[contains(@class, "vertical-tabs__menu-item")]/a/strong[text()="Meta tags"]');
  }

  /**
   * Tests the Metatag Async Widget with Seven.
   */
  public function testMetatagAsyncWidgetSeven() {
    \Drupal::service('theme_installer')->install(['seven']);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'seven')
      ->save();

    $this->rebuildAll();
    $this->doTestMetatagAsyncWidget('//div[@id="edit-advanced"]/details/summary/span[text()="Meta tags"]');
  }

  /**
   * Tests the Metatag Async Widget with Claro.
   */
  public function testMetatagAsyncWidgetClaro() {
    if (!\Drupal::service('extension.list.theme')->exists('claro')) {
      $this->markTestSkipped('The Claro theme does not exist');
    }
    \Drupal::service('theme_installer')->install(['claro']);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'claro')
      ->save();

    $this->rebuildAll();
    $this->doTestMetatagAsyncWidget('//div[@id="edit-advanced"]/details/summary[text()="Meta tags"]');
  }

  /**
   * Tests the Metatag Async Widget with Bartik.
   */
  public function testMetatagAsyncWidgetBartik() {
    if (!\Drupal::service('extension.list.theme')->exists('bartik')) {
      $this->markTestSkipped('The Bartik theme does not exist');
    }
    \Drupal::service('theme_installer')->install(['bartik']);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'bartik')
      ->save();

    $this->rebuildAll();
    $this->doTestMetatagAsyncWidget('//li[contains(@class, "vertical-tabs__menu-item")]/a/strong[text()="Meta tags"]');
  }

  /**
   * Tests the Metatag Async Widget.
   */
  public function doTestMetatagAsyncWidget($metatag_details_xpath) {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add a Metatag field to the content type.
    $this->drupalGet('admin/structure/types/manage/metatag_node/fields/add-field');
    $assert->fieldExists('new_storage_type')->setValue('metatag');
    $assert->waitForField('label');
    $assert->fieldExists('label')->setValue('Meta tags');
    $assert->waitForText('Machine name');
    $page->pressButton('Save and continue');
    $page->pressButton('Save field settings');
    $page->pressButton('Save settings');

    // Set the form display.
    $this->drupalGet('admin/structure/types/manage/metatag_node/form-display');
    $assert->fieldExists('edit-fields-field-meta-tags-type')->setValue('metatag_async_widget_firehose');
    $assert->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $assert->pageTextContains('Your settings have been saved.');

    // Create a node.
    $this->drupalGet('node/add/metatag_node');
    $assert->fieldExists('edit-title-0-value')->setValue($this->getRandomGenerator()->sentences('4'));
    $assert->fieldNotExists('field_meta_tags[0][basic][abstract]');
    $assert->waitForElement('xpath', $metatag_details_xpath)->click();
    $assert->waitForButton('Customize meta tags')->click();
    $assert->waitForText('Configure the meta tags below.');
    $assert->pageTextContains('Configure the meta tags below.');
    // Ensure the summary is not duplicated.
    $selector = substr($metatag_details_xpath, strrpos($metatag_details_xpath, '/') + 1);
    $this->htmlOutput();
    $assert->elementsCount('xpath', '//' . $selector, 1);
    $abstract = $this->getRandomGenerator()->sentences(10);
    $field = $assert->fieldExists('field_meta_tags[0][basic][abstract]');
    // Ensure that field has been loaded via AJAX. See
    // \Drupal\Component\Utility\Html::getUniqueId().
    $this->assertRegExp('/^edit-field-meta-tags-0-basic-abstract--([a-zA-Z0-9_-])*$/', $field->getAttribute('id'));
    $field->setValue($abstract);
    $page->pressButton('Save');

    // For some reason we need to sleep here before trying to click edit.
    // @todo Without this the Seven test fail, work out why.
    sleep(1);
    // Edit the node and ensure the abstract is saved.
    $assert->waitForLink('Edit')->click();
    $assert->fieldNotExists('field_meta_tags[0][basic][abstract]');
    $assert->waitForElement('xpath', $metatag_details_xpath)->click();
    $assert->waitForButton('Customize meta tags')->click();
    $assert->waitForText('Configure the meta tags below.');
    $assert->fieldValueEquals('field_meta_tags[0][basic][abstract]', $abstract);
  }

}
