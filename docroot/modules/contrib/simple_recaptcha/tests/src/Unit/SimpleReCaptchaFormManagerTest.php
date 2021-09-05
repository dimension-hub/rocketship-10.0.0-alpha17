<?php

namespace Drupal\Tests\simple_recaptcha\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\simple_recaptcha\SimpleReCaptchaFormManager;

/**
 * Class SimpleReCaptchaFormManagerTest.
 *
 * @group simple_recaptcha
 *
 * @coversDefaultClass \Drupal\simple_recaptcha\SimpleReCaptchaFormManager
 */
class SimpleReCaptchaFormManagerTest extends UnitTestCase {

  /**
   * Test the formIdInList().
   *
   * @covers ::formIdInList
   *
   * @dataProvider dataFormIdProvider
   */
  public function testFormIdInList($formId, $list, bool $expected) {
    $result = SimpleReCaptchaFormManager::formIdInList($formId, $list);
    $this->assertSame($result, $expected);
  }

  /**
   * Data to test form id is detected or not.
   */
  public function dataFormIdProvider() {
    return [
      ['user_login_form', ['user_login_form', 'user_pass', 'user_register_form'], TRUE],
      ['user_pass', ['user_login_form', 'user_pass', 'user_register_form'], TRUE],
      ['user_register_form', ['user_login_form', 'user_pass', 'user_register_form'], TRUE],
      ['user_login_form', ['user_login*', 'user_register*'], TRUE],
      ['user_pass', ['user_login*', 'user_register*'], FALSE],
      ['user_register_form', ['user_login*', 'user_register*'], TRUE],
      ['user_login_form', ['user_*'], TRUE],
      ['user_pass', ['user_*', 'user_*'], TRUE],
      ['user_register_form', ['user_*', 'user_*'], TRUE],
      ['user_login_form', ['user_*_form'], TRUE],
      ['user_pass', ['user_*_form'], FALSE],
      ['user_register_form', ['user_*_form'], TRUE],
      ['user_login_form', ['*user*'], TRUE],
      ['user_pass', ['*user*'], TRUE],
      ['user_register_form', ['*user*'], TRUE],
      ['user_login_form', ['*_user*'], FALSE],
      ['user_pass', ['*_user*'], FALSE],
      ['user_register_form', ['*_user*'], FALSE],
      ['user_login_form', ['user_other*', 'user_else*'], FALSE],
      ['user_pass', ['user_other*', 'user_else*'], FALSE],
      ['user_register_form', ['user_other*', 'user_else*'], FALSE],
    ];
  }

}
