<?php

namespace Drupal\Tests\simple_recaptcha\Functional;

/**
 * Tests for the Simple reCAPTCHA module.
 *
 * @group simple_recaptcha
 */
class SimpleRecaptchaTest extends SimpleRecaptchaTestBase {

  /**
   * Tests that the configuration page can be reached.
   */
  public function testConfigPage() {
    // Login.
    $this->drupalLogin($this->user);

    // Permissions / config page existance check.
    $this->drupalGet('admin/config/services/simple_recaptcha');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test the config form.
   */
  public function testConfigForm() {
    // Login.
    $this->drupalLogin($this->user);

    // Test default configuration.
    $this->drupalGet('admin/config/services/simple_recaptcha');
    $this->assertSession()->statusCodeEquals(200);

    $config = $this->config('simple_recaptcha.config');
    $this->assertSession()->fieldValueEquals(
      'recaptcha_type',
      $config->get('recaptcha_type'),
    );
    $this->assertSession()->fieldValueEquals(
      'form_ids',
      $config->get('form_ids'),
    );

    $this->assertSession()->fieldValueEquals(
      'recaptcha_use_globally',
      $config->get('recaptcha_use_globally'),
    );

    // Test config form submission,
    // reCAPTCHA v2 provides test keys.
    // @see https://developers.google.com/recaptcha/docs/faq#id-like-to-run-automated-tests-with-recaptcha.-what-should-i-do
    $this->submitForm([
      'recaptcha_type' => 'v2',
      'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
      'secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
      'form_ids' => 'user_login_form,user_pass,user_register_form',
    ], t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Ensure that config has been saved.
    $config = $this->config('simple_recaptcha.config');
    $this->assertSession()->fieldValueEquals(
      'recaptcha_type',
      $config->get('recaptcha_type'),
    );
    $this->assertSession()->fieldValueEquals(
      'site_key',
      $config->get('site_key'),
    );
    $this->assertSession()->fieldValueEquals(
      'site_key',
      $config->get('site_key'),
    );
    $this->assertSession()->fieldValueEquals(
      'form_ids',
      $config->get('form_ids'),
    );
  }

  /**
   * Test if api.js file is properly loaded when reCAPTCHA is used globally.
   */
  public function testGlobalJsLoaded() {
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/services/simple_recaptcha');
    $this->submitForm([
      'recaptcha_type' => 'v2',
      'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
      'secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
      'form_ids' => 'user_login_form,user_pass,user_register_form',
      'recaptcha_use_globally' => TRUE,
    ], t('Save configuration'));
    drupal_flush_all_caches();
    $this->drupalLogout();
    $this->drupalGet('/user/login');
    $this->assertSession()->pageTextContains('Log in');
    $this->assertRaw('<script src="https://www.recaptcha.net/recaptcha/api.js" defer async></script>');
  }

}
