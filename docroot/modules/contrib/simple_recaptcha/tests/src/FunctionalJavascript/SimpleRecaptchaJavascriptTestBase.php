<?php

namespace Drupal\Tests\simple_recaptcha\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * JavaScripts tests for the Simple reCAPTCHA module.
 *
 * @group simple_recaptcha
 */
class SimpleRecaptchaJavascriptTestBase extends WebDriverTestBase {

  /**
   * WebAssert object.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $webAssert;

  /**
   * DocumentElement object.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $page;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['simple_recaptcha', 'simple_recaptcha_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'bartik';

  /**
   * A simple user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->page = $this->getSession()->getPage();
    $this->webAssert = $this->assertSession();
    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'administer simple_recaptcha',
    ],
      'webadmin');
    $this->configureModule();
  }

  /**
   * Helper to configure the module.
   *
   * We need to set up reCAPTCHA test keys to make form alteration works.
   * Currently there's no way to set default config for testing.
   *
   * @see https://www.drupal.org/project/drupal/issues/913086
   */
  public function configureModule() {
    $config = [
      'recaptcha_type' => 'v2',
      'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
      'secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
      'form_ids' => 'user_login_form,user_pass,user_register_form,simple_recaptcha_test',
    ];
    \Drupal::configFactory()->getEditable('simple_recaptcha.config')
      ->setData($config)
      ->save();
  }

  /**
   * Tests that the configuration page can be reached.
   */
  public function testLoginPage() {
    $config = $this->config('simple_recaptcha.config');
    $this->drupalGet('/user/login');

    // reCAPTCHA site key exists in drupalSettings.
    $this->assertJsCondition('drupalSettings.simple_recaptcha.sitekey === "' . $config->get('site_key') . '";');
  }

}
