<?php

namespace Drupal\Tests\simple_recaptcha_webform\FunctionalJavascript;

/**
 * JavaScripts tests for the Simple reCAPTCHA webform module.
 *
 * @group simple_recaptcha_webform
 */
class SimpleRecaptchaWebformJavascriptTest extends SimpleRecaptchaWebformJavascriptTestBase {

  /**
   * Check if reCAPTCHA validation is added to contact webform.
   */
  public function testContactWebform() {
    $this->configureModule();
    $this->drupalGet('webform/simple_recaptcha_v2');
    $assert = $this->assertSession();
    $assert->hiddenFieldExists('simple_recaptcha_type');
    $assert->hiddenFieldValueEquals('simple_recaptcha_type', 'v2');


    // Fill in required fields
    $assert->fieldExists('name')->setValue('test name');
    $assert->fieldExists('email')->setValue('test@example.com');
    $assert->fieldExists('subject')->setValue('subject');
    $assert->fieldExists('message')->setValue('message');
    $this->getSession()->getPage()->pressButton('Submit');

    $this->assignNameToCaptchaIframe();
    $this->getSession()->switchToIFrame('recaptcha-iframe');
    $this->click('.recaptcha-checkbox');
    // Give it a while as reCAPTCHA throbber likes to spin for a while..
    $this->getSession()->wait('2000');
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->pressButton('Submit');
    $assert->pageTextContains('Your message has been sent.');
    $this->htmlOutput();
  }

  /**
   * Verify that libraries are loaded correctly for different reCAPTCHA versions.
   */
  public function testRecaptchaLibraries() {
    // Configure v3 reCAPTCHA and visit some pages so they get cached.
    $this->configureModule('v3');
    $this->drupalGet('user/password');
    // reCAPTCHA badge should be present.
    $this->assertSession()->elementExists('css', '.grecaptcha-badge');
    $this->drupalGet('webform/simple_recaptcha_v2');
    // reCAPTCHA badge shouldn't be there as it only appears for v3 reCAPTCHA.
    $this->assertSession()->elementNotExists('css', '.grecaptcha-badge');

    // Switch reCAPTCHA to v2.
    $this->configureModule();
    $this->drupalGet('user/password');
    // reCAPTCHA badge shouldn't be there as it only appears for v3 reCAPTCHA.
    $this->assertSession()->elementNotExists('css', '.grecaptcha-badge');

    $this->drupalGet('webform/simple_recaptcha_v2');
    // reCAPTCHA badge shouldn't be there as it only appears for v3 reCAPTCHA.
    $this->assertSession()->elementNotExists('css', '.grecaptcha-badge');
  }

  /**
   * Assigns a name to the reCAPTCHA iframe.
   *
   * @see \Drupal\Tests\media\FunctionalJavascript\CKEditorIntegrationTest::assignNameToCkeditorIframe
   * assignNameToCkeditorIframe
   *
   * @TODO duplicate - move this logic to some sort of Trait.
   */
  protected function assignNameToCaptchaIframe() {
    $javascript = <<<JS
(function(){
  var iframes = document.getElementsByTagName('iframe');
    for(var i = 0; i < iframes.length; i++){
        var f = iframes[i];
        f.name = 'recaptcha-iframe';
    }
})()
JS;
    $this->getSession()->evaluateScript($javascript);
  }

}
