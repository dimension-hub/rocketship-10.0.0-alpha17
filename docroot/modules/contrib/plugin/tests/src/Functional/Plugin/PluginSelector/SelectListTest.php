<?php

namespace Drupal\Tests\plugin\Functional\Plugin\PluginSelector;

use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\plugin\Plugin\Plugin\PluginSelector\SelectList
 *
 * @group Plugin
 */
class SelectListTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['filter', 'plugin_test_helper'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the element.
   */
  public function testElement() {
    $this->doTestElement(FALSE);
    $this->doTestElement(TRUE);
  }

  public function buildFormPath(array $allowed_selectable_plugin_ids, $tree, $always_show_selector = FALSE) {
    return sprintf('plugin_test_helper-plugin_selector-advanced_plugin_selector_base/%s/plugin_select_list/%d/%d', implode(',', $allowed_selectable_plugin_ids), (int) $tree, (int) $always_show_selector);
  }

  /**
   * Tests the element.
   *
   * @param bool $tree
   *   Whether to test the element with #tree = TRUE or not.
   */
  public function doTestElement($tree) {
    $name_prefix = $tree ? 'tree[plugin][container]' : 'container';
    $change_button_name = $tree ? 'tree__plugin__container__select__container__change' : 'container__select__container__change';

    // Test the presence of default elements without available plugins.
    $path = $this->buildFormPath(['none'], $tree);
    $this->drupalGet($path);
    $this->assertNoFieldByName($name_prefix . '[select][container][container][plugin_id]');
    $this->assertEmpty($this->getSession()->getDriver()->find(sprintf('//input[@name="%s"]', $change_button_name)));
    $this->assertText(t('There are no available options.'));

    // Test the presence of default elements with one available plugin.
    $path = $this->buildFormPath(['plugin_test_helper_configurable_plugin'], $tree);
    $this->drupalGet($path);
    $this->assertNoFieldByName($name_prefix . '[select][container][plugin_id]');
    $this->assertEmpty($this->getSession()->getDriver()->find(sprintf('//input[@name="%s"]', $change_button_name)));
    $this->assertNoText(t('There are no available options.'));

    // Test the presence of default elements with multiple available plugins.
    $path = $this->buildFormPath(['plugin_test_helper_plugin', 'plugin_test_helper_configurable_plugin'], $tree);
    $this->drupalGet($path);
    $this->assertFieldByName($name_prefix . '[select][container][plugin_id]');
    $this->assertNotEmpty($this->getSession()->getDriver()->find(sprintf('//input[@name="%s"]', $change_button_name)));
    $this->assertNoText(t('There are no available options.'));

    // Choose a plugin.
    $this->drupalPostForm(NULL, [
      $name_prefix . '[select][container][plugin_id]' => 'plugin_test_helper_plugin',
    ], t('Choose'));
    $this->assertFieldByName($name_prefix . '[select][container][plugin_id]');
    $this->assertNotEmpty($this->getSession()->getDriver()->find(sprintf('//input[@name="%s"]', $change_button_name)));

    // Change the plugin.
    $this->drupalPostForm(NULL, [
      $name_prefix . '[select][container][plugin_id]' => 'plugin_test_helper_configurable_plugin',
    ], t('Choose'));
    $this->assertFieldByName($name_prefix . '[select][container][plugin_id]');
    $this->assertNotEmpty($this->getSession()->getDriver()->find(sprintf('//input[@name="%s"]', $change_button_name)));

    // Submit the form.
    $foo = $this->randomString();
    $this->drupalPostForm(NULL, [
      $name_prefix . '[select][container][plugin_id]' => 'plugin_test_helper_configurable_plugin',
      $name_prefix . '[plugin_form][foo]' => $foo,

    ], t('Submit'));

    $state = \Drupal::state();
    /** @var \Drupal\Component\Plugin\PluginInspectionInterface|\Drupal\Component\Plugin\ConfigurablePluginInterface $selected_plugin */
    $selected_plugin = $state->get('plugin_test_helper_advanced_plugin_selector_base');
    $this->assertEqual($selected_plugin->getPluginId(), 'plugin_test_helper_configurable_plugin');
    $this->assertEqual($selected_plugin->getConfiguration(), [
      'foo' => $foo,
    ]);
  }
}
