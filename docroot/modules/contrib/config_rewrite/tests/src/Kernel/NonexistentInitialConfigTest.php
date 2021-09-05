<?php

namespace Drupal\Tests\config_rewrite\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\config_rewrite\ConfigRewriter
 * @group config_rewrite
 */
class NonexistentInitialConfigTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'user', 'config_rewrite', 'config_rewrite_nonexisting', 'language'];

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $activeConfigStorage;

  /**
   * The configuration rewriter.
   *
   * @var \Drupal\config_rewrite\ConfigRewriterInterface
   */
  protected $configRewriter;

  /**
   * The language config factory override service.
   *
   * @var \Drupal\language\Config\LanguageConfigFactoryOverrideInterface
   */
  protected $languageConfigFactoryOverride;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->configRewriter = $this->container->get('config_rewrite.config_rewriter');
    $this->activeConfigStorage = $this->container->get('config.storage');
    $this->languageConfigFactoryOverride = $this->container->get('language.config_factory_override');
    $this->installSchema('system', ['sequence']);
    $this->installEntitySchema('user_role');
  }

  /**
   * @covers ::rewriteModuleConfig
   * @covers ::rewriteConfig
   * @expectedException \Drupal\config_rewrite\Exception\NonexistentInitialConfigException
   */
  function testConfigRewrite() {
    $this->configRewriter->rewriteModuleConfig('config_rewrite_nonexisting');
  }

}
