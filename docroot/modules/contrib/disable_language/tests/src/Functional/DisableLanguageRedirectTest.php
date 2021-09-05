<?php

namespace Drupal\Tests\disable_language\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\disable_language\EventSubscriber\DisabledLanguagesEventSubscriber
 * @group disable_language
 */
class DisableLanguageRedirectTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'language', 'node', 'disable_language'];

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stable';

  /**
   * @var NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

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

    if ($this->profile !== 'standard') {
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    }

    $this->node = $this->drupalCreateNode(['title' => $this->randomString()]);
    $this->node->addTranslation('nl', ['title' => $this->randomString()]);
    $this->node->addTranslation('fr', ['title' => $this->randomString()]);
    $this->node->save();
  }

  /**
   * Test redirects.
   */
  public function testRedirects() {
    $allowed_user = $this->createUser(['view disabled languages']);
    $this->drupalLogin($allowed_user);
    $this->drupalGet('fr/node/' . $this->node->id());
    $this->assertSession()->addressEquals('fr/node/' . $this->node->id());
    $this->drupalLogout();

    $this->drupalGet('fr/node/' . $this->node->id());
    $this->assertSession()->addressEquals('nl');

    \Drupal::configFactory()
      ->getEditable('disable_language.settings')
      ->set('redirect_override_routes', ['entity.node.canonical'])
      ->save();
    drupal_flush_all_caches();
    $this->drupalGet('fr/node/' . $this->node->id());
    $this->assertSession()->addressEquals('nl/node/' . $this->node->id());

    \Drupal::configFactory()
      ->getEditable('disable_language.settings')
      ->set('redirect_override_routes', [''])
      ->set('exclude_request_path', ['pages' => '/node/*'])
      ->save();
    drupal_flush_all_caches();
    $this->drupalGet('fr/node/' . $this->node->id());
    $this->assertSession()->addressEquals('fr/node/' . $this->node->id());
  }

  /**
   * Tests redirect when the chosen language negotiation method is url domain.
   */
  public function testRedirectDifferentDomain() {
    \Drupal::configFactory()
      ->getEditable('language.negotiation')
      ->set('url.source', LanguageNegotiationUrl::CONFIG_DOMAIN)
      ->set('url.domains', ['nl' => 'nl.example.com', 'fr' => \Drupal::request()->getHost()])
      ->save();

    // Do not actually follow the redirects, since we are using a non-existing
    // domain.
    $this->getSession()->getDriver()->getClient()->followRedirects(false);
    $this->maximumMetaRefreshCount = 0;

    // Check for the correct redirect status code and presence of the external
    // domain in the Location-header.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->statusCodeEquals(307);
    $this->assertSession()->responseHeaderContains('Location', 'nl.example.com');
  }
}
