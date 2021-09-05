<?php

namespace Drupal\Tests\dropsolid_rocketship_profile\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Test base that provides Dropsolid install profile specific things.
 */
abstract class RocketshipBrowserTestBase extends BrowserTestBase {

  /**
   * Something wrong with search_api_db test config, we think.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'dropsolid_rocketship_profile';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Don't mind solving CAPTCHAs during test run.
    $this->container->get('module_installer')->uninstall(['captcha']);
    // Setting a setting may be cleaner but isn't supported in Drupal 8 until
    // https://www.drupal.org/project/captcha/issues/2836076
    // \Drupal::config('captcha.settings')->set('disable_captcha', TRUE)
    // ->save();
  }

  /**
   * Login as webadmin.
   */
  public function drupalLoginAsWebAdmin() {
    // Create and log in as webadmin. Always uid 2 with our profile.
    /** @var \Drupal\user\UserInterface $webadmin */
    $webadmin = User::load(2);
    $webadmin->setPassword('test');
    $webadmin->save();
    $webadmin->passRaw = 'test';

    $this->drupalLogin($webadmin);
  }

}
