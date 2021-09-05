<?php

namespace Drupal\Tests\simple_recaptcha_webform\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test base for simple_recaptcha_webform module.
 *
 * @group simple_recaptcha_webform
 */
class SimpleRecaptchaWebformTestBase extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'simple_recaptcha',
    'webform',
    'webform_ui',
    'simple_recaptcha_webform',
    'simple_recaptcha_webform_test',
  ];

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'bartik';

  /**
   * A simple user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() {
    parent::setUp();
    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'administer simple_recaptcha',
      'access any webform configuration',
      'administer webform',
    ],
      'webadmin');
  }

  /**
   * Verify that webform admin pages are accessible.
   */
  public function testWebformAdminPage() {
    // Permissions / config page existance check.
    $this->drupalLogin($this->user);
    $this->drupalGet('/admin/structure/webform');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();
  }

}
