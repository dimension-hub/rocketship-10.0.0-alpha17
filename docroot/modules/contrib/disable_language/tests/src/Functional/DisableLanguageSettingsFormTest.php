<?php

namespace Drupal\Tests\disable_language\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\disable_language\Form\DisableLanguageSettings
 * @group disable_language
 */
class DisableLanguageSettingsFormTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'language', 'disable_language'];

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test form validation.
   */
  public function testSettingsValidation() {
    $this->drupalGet('admin/config/regional/language/disable_language');
    $this->assertField('redirect_override_routes');
    $edit = ['redirect_override_routes' => 'foo.bar',];
    $this->submitForm($edit,t('Save configuration'));
    $this->assertSession()->pageTextContains('Route "foo.bar" does not exist.');
  }

  /**
   * Test form submit.
   */
  public function testSettingsSaved() {
    $this->drupalGet('admin/config/regional/language/disable_language');
    $this->assertSession()->statusCodeEquals(200);

    $config = $this->config('disable_language.settings');
    $this->assertSession()->fieldValueEquals(
      'redirect_override_routes',
      implode("\n", $config->get('redirect_override_routes'))
    );
    $this->assertSession()->fieldValueEquals(
      'exclude_request_path[pages]',
      $config->get('exclude_request_path')['pages']
    );
    $edit = [
      'redirect_override_routes' => 'system.admin_content',
      'exclude_request_path[pages]' => '/user/*'
    ];
    $this->submitForm($edit,t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->drupalGet('admin/config/regional/language/disable_language');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'redirect_override_routes',
      'system.admin_content'
    );
    $this->assertSession()->fieldValueEquals(
      'exclude_request_path[pages]',
      '/user/*'
    );
  }

}
