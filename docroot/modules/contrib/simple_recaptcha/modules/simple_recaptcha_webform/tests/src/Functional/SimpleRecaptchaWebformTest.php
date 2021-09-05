<?php

namespace Drupal\Tests\simple_recaptcha_webform\Functional;

/**
 * Tests for the Simple reCAPTCHA webform module.
 *
 * @group simple_recaptcha_webform
 */
class SimpleRecaptchaWebformTest extends SimpleRecaptchaWebformTestBase {

  /**
   * Verify that webform has been installed from config.
   */
  public function testWebformPage() {
    // Permissions / config page existance check.
    $this->drupalLogin($this->user);
    $this->drupalGet('/admin/structure/webform/manage/simple_recaptcha_v2');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/admin/structure/webform/manage/simple_recaptcha_v2/handlers/recaptcha/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();
  }

}
