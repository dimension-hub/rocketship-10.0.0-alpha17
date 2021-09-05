<?php

namespace Drupal\Tests\simple_recaptcha\FunctionalJavascript;

/**
 * JavaScripts tests for the Simple reCAPTCHA module.
 *
 * @group simple_recaptcha
 */
class SimpleRecaptchaJavascriptTest extends SimpleRecaptchaJavascriptTestBase {

  /**
   * Check if reCAPTCHA validation is added to user login form.
   */
  public function testLoginForm() {
    $config = $this->config('simple_recaptcha.config');
    $this->drupalGet('/user/login');

    // reCAPTCHA site key exists in drupalSettings.
    $this->assertJsCondition('drupalSettings.simple_recaptcha.sitekey === "' . $config->get('site_key') . '";');

    // Check if hidden field added by the module are present.
    $this->webAssert->hiddenFieldExists('simple_recaptcha_token');

    // This field shoudln't exist as it's added only when we configure v3 reCAPTCHA.
    $this->webAssert->hiddenFieldNotExists('simple_recaptcha_message');

    // Try to click on Log in button and render reCAPTCHA widget.
    $this->page->pressButton('Log in');
    $this->webAssert->waitForElement('css', 'recaptcha-visible');

    // reCAPTCHA doesn't provide consistent iframe name so we need to update it.
    $this->assignNameToCaptchaIframe();
    $this->getSession()->switchToIFrame('recaptcha-iframe');
    $this->assertStringCOntainsString('This reCAPTCHA is for testing purposes only. Please report to the site admin if you are seeing this.', $this->page->getContent());
    $this->htmlOutput($this->page->getHtml());

    // Try to log in, which should fail.
    $this->getSession()->switchToIFrame();
    $user = $this->drupalCreateUser([]);
    $edit = ['name' => $user->getAccountName(), 'pass' => $user->passRaw];
    $this->submitForm($edit, t('Log in'));

    // Check if reCAPTCHA wrapper has error class.
    $error_wrapper = $this->page->find('css', '.recaptcha-error');
    $this->assertTrue($error_wrapper->isVisible());

    // And we're still at user login page.
    $this->webAssert->addressEquals('/user/login');
    $this->htmlOutput($this->page->getHtml());
  }

  /**
   * Test reCAPTCHA protected form containing file upload widget.
   */
  public function testFileUploadWidget() {
    // JS preprocessing is disabled in tests by default,.
    // @see https://www.drupal.org/project/drupal/issues/2467937
    $this->config('system.performance')
      ->set('js.preprocess', TRUE)
      ->save();
    drupal_flush_all_caches();

    // Navigate to custom form.
    $this->drupalGet('/simple_recaptcha_test/form');

    // Fill in required field.
    $assert = $this->assertSession();
    $assert->fieldExists('recaptcha_test_name')->setValue('test name');

    // Initial form submit - triggers reCAPTCHA checkbox.
    $this->page->pressButton('Form submit');

    // Go through the reCAPTCHA iframe magic.
    $this->assignNameToCaptchaIframe();
    $this->getSession()->switchToIFrame('recaptcha-iframe');
    $this->click('.recaptcha-checkbox');
    // Give it a while as reCAPTCHA throbber likes to spin for a while..
    $this->getSession()->wait('2000');
    $this->getSession()->switchToIFrame();

    $this->page->pressButton("simple-recaptcha-submit-button");
    $this->htmlOutput();
    $assert->pageTextContains('Clicked on edit-submit');
  }

  /**
   * Submit form as anonymous user and ensure that this submission doesn't keep the session cookies.
   */
  public function testSessionData() {
    // Set up the module and visit password reset page.
    $this->drupalGet('/user/password');
    $user = $this->drupalCreateUser([]);
    $edit = ['name' => $user->getAccountName()];

    // Handle reCAPTCHA widget.
    $this->page->pressButton('Submit');
    $this->assignNameToCaptchaIframe();
    $this->getSession()->switchToIFrame('recaptcha-iframe');
    $this->click('.recaptcha-checkbox');
    $this->getSession()->switchToIFrame();
    // Give it a while as reCAPTCHA throbber likes to spin for a while..
    $this->getSession()->wait('2000');

    // Final form submission, which should leave session cookies empty.
    $this->submitForm($edit, t('Submit'));
    $this->assertSession()->pageTextContains(t('Further instructions have been sent to your email address.'));
    $this->assertEmpty($this->getSessionCookies()->toArray());
  }

  /**
   * Assigns a name to the reCAPTCHA iframe.
   *
   * @see \Drupal\Tests\media\FunctionalJavascript\CKEditorIntegrationTest::assignNameToCkeditorIframe
   * assignNameToCkeditorIframe
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
