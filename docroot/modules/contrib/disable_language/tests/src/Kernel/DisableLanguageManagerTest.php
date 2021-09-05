<?php

namespace Drupal\Tests\disable_language\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * @coversDefaultClass \Drupal\disable_language\DisableLanguageManager
 * @group disable_language
 */
class DisableLanguageManagerTest extends KernelTestBase {

  /**
   * The Disable language manager.
   *
   * @var \Drupal\disable_language\DisableLanguageManager
   */
  protected $disableLanguageManager;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'language', 'disable_language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->disableLanguageManager = \Drupal::service('disable_language.disable_language_manager');
    $this->installConfig(['language']);
    $this->installConfig(['disable_language']);
    ConfigurableLanguage::load('en')
      ->setWeight(0)
      ->save();
    ConfigurableLanguage::createFromLangcode('nl')
      ->setWeight(1)
      ->save();
    ConfigurableLanguage::createFromLangcode('fr')
      ->setWeight(2)
      ->setThirdPartySetting('disable_language', 'disable', 1)
      ->setThirdPartySetting('disable_language', 'redirect_language', 'nl')
      ->save();
  }

  /**
   * Test getConfigurableLanguages method.
   */
  public function testGetConfigurableLanguages() {
    $available_languages = \Drupal::languageManager()->getLanguages();
    $configurable_languages = $this->disableLanguageManager
      ->getConfigurableLanguages();

    $this->assertEquals(array_keys($available_languages),
      array_keys($configurable_languages));
  }

  /**
   * Test getDisabledLanguages method.
   */
  public function testGetDisabledLanguages() {
    $disabled_languages = $this->disableLanguageManager->getDisabledLanguages();
    $this->assertTrue(count($disabled_languages) === 1 &&
      array_key_exists('fr', $disabled_languages));
  }

  /**
   * Test getEnabledLanguages.
   */
  public function testEnabledLanguages() {
    $enabled_languages = $this->disableLanguageManager->getEnabledLanguages();
    $this->assertTrue(count($enabled_languages) &&
      !array_diff(['en', 'nl'], array_keys($enabled_languages)));
  }

  /**
   * Test isCurrentLanguageDisabled & getFallbackLanguage.
   */
  public function testIsCurrentLanguageDisabledAndHasFallback() {
    $this->assertFalse($this->disableLanguageManager
      ->isCurrentLanguageDisabled());
    $this->assertFalse($this->disableLanguageManager->getFallbackLanguage());
    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('default_langcode', 'fr')
      ->save();
    $this->assertTrue($this->disableLanguageManager
      ->isCurrentLanguageDisabled());
    $this->assertTrue($this->disableLanguageManager
      ->getFallbackLanguage() === 'nl');
  }

  /**
   * Test getFirstEnabledLanguage.
   */
  public function testGetFirstEnabledLanguage() {
    $this->assertTrue($this->disableLanguageManager
      ->getFirstEnabledLanguage()->getId() === 'en');
  }

}
