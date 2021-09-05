<?php

namespace Drupal\Tests\plugin\Unit\ParamConverter;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\plugin\ParamConverter\PluginDefinitionConverter;
use Drupal\plugin\PluginType\PluginTypeInterface;
use Drupal\plugin\PluginType\PluginTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\plugin\ParamConverter\PluginDefinitionConverter
 *
 * @group Plugin
 */
class PluginDefinitionConverterTest extends UnitTestCase {

  /**
   * The plugin manager.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $pluginTypeManager;

  /**
   * The system under test.
   *
   * @var \Drupal\plugin\ParamConverter\PluginDefinitionConverter
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->pluginTypeManager = $this->prophesize(PluginTypeManagerInterface::class);

    $this->sut = new PluginDefinitionConverter($this->pluginTypeManager->reveal());
  }

  /**
   * @covers ::applies
   * @covers ::validateParameterDefinition
   * @covers ::getConverterDefinitionConstraint
   * @covers ::getConverterDefinition
   * @covers ::getConverterDefinitionKey
   * @covers ::__construct
   *
   * @dataProvider provideApplies
   */
  public function testApplies($expected, $definition) {
    $name = 'foo_bar';
    $route = $this->prophesize(Route::class);


    $this->assertSame($expected, $this->sut->applies($definition, $name, $route->reveal()));
  }

  /**
   * Provides data to self::testApplies().
   */
  public function provideApplies() {
    $data = [];

    $data['applies-because-implicitly-enabled'] = [TRUE, [
      'plugin.plugin_definition' => [
        'plugin_type_id' => 'foo.bar',
      ],
    ]];
    $data['applies-because-explicitly-enabled'] = [TRUE, [
      'plugin.plugin_definition' => [
        'enabled' => TRUE,
        'plugin_type_id' => 'foo.bar',
      ],
    ]];
    $data['applies-not-because-disabled'] = [FALSE, [
      'plugin.plugin_definition' => [
        'enabled' => FALSE,
        'plugin_type_id' => 'foo.bar',
      ],
    ]];
    $data['applies-not-because-non-existent'] = [FALSE, []];

    return $data;
  }

  /**
   * @covers ::convert
   * @covers ::doConvert
   * @covers ::validateParameterDefinition
   * @covers ::getConverterDefinitionConstraint
   * @covers ::getConverterDefinition
   * @covers ::getConverterDefinitionKey
   * @covers ::__construct
   */
  public function testConvertWithExceptionReturnsNull() {
    $plugin_type_id = 'foo_bar.baz';
    $definition = [
      'plugin.plugin_definition' => [
        'plugin_type_id' => $plugin_type_id,
      ],
    ];
    $plugin_id = 'foozaar.bazaar';
    $name = 'foo_bar';
    $defaults = [];

    $plugin_manager = $this->prophesize(PluginManagerInterface::class);
    $plugin_manager->hasDefinition($plugin_id)->willReturn(TRUE);
    $plugin_manager->getDefinition($plugin_id)->willThrow(new PluginNotFoundException($plugin_id));

    $plugin_type = $this->prophesize(PluginTypeInterface::class);
    $plugin_type->getPluginManager()->willReturn($plugin_manager);

    $this->pluginTypeManager->getPluginType($plugin_type_id)->willReturn($plugin_type);

    $original_error_reporting = error_reporting();
    error_reporting($original_error_reporting & ~E_USER_WARNING);
    $this->assertNull($this->sut->convert($plugin_id, $definition, $name, $defaults));
    error_reporting($original_error_reporting);
  }

  /**
   * @covers ::convert
   * @covers ::doConvert
   * @covers ::validateParameterDefinition
   * @covers ::getConverterDefinitionConstraint
   * @covers ::getConverterDefinition
   * @covers ::getConverterDefinitionKey
   * @covers ::__construct
   */
  public function testConvertWithKnownPlugin() {
    $plugin_type_id = 'foo_bar.baz';
    $definition = [
      'plugin.plugin_definition' => [
        'plugin_type_id' => $plugin_type_id,
      ],
    ];
    $plugin_id = 'foozaar.bazaar';
    $name = 'foo_bar';
    $defaults = [];

    $plugin_definition = $this->prophesize(PluginDefinitionInterface::class);

    $plugin_manager = $this->prophesize(PluginManagerInterface::class);
    $plugin_manager->hasDefinition($plugin_id)->willReturn(TRUE);
    $plugin_manager->getDefinition($plugin_id)->willReturn($plugin_definition);

    $plugin_type = $this->prophesize(PluginTypeInterface::class);
    $plugin_type->getPluginManager()->willReturn($plugin_manager);
    $plugin_type->ensureTypedPluginDefinition($plugin_definition->reveal())->willReturnArgument(0);

    $this->pluginTypeManager->getPluginType($plugin_type_id)->willReturn($plugin_type);

    $this->assertSame($plugin_definition->reveal(), $this->sut->convert($plugin_id, $definition, $name, $defaults));
  }

  /**
   * @covers ::convert
   * @covers ::doConvert
   * @covers ::validateParameterDefinition
   * @covers ::getConverterDefinitionConstraint
   * @covers ::getConverterDefinition
   * @covers ::getConverterDefinitionKey
   * @covers ::__construct
   */
  public function testConvertWithUnknownPlugin() {
    $plugin_type_id = 'foo_bar.baz';
    $definition = [
      'plugin.plugin_definition' => [
        'plugin_type_id' => $plugin_type_id,
      ],
    ];
    $plugin_id = 'foozaar.bazaar';
    $name = 'foo_bar';
    $defaults = [];

    $plugin_manager = $this->prophesize(PluginManagerInterface::class);
    $plugin_manager->hasDefinition($plugin_id)->willReturn(FALSE);

    $plugin_type = $this->prophesize(PluginTypeInterface::class);
    $plugin_type->getPluginManager()->willReturn($plugin_manager);

    $this->pluginTypeManager->getPluginType($plugin_type_id)->willReturn($plugin_type);

    $original_error_reporting = error_reporting();
    error_reporting($original_error_reporting & ~E_USER_WARNING);
    $this->assertNull($this->sut->convert($plugin_id, $definition, $name, $defaults));
    error_reporting($original_error_reporting);
  }

  /**
   * @covers ::convert
   * @covers ::doConvert
   * @covers ::validateParameterDefinition
   * @covers ::getConverterDefinitionConstraint
   * @covers ::getConverterDefinition
   * @covers ::getConverterDefinitionKey
   * @covers ::__construct
   */
  public function testConvertWithInvalidDefinition() {
    // Leave out the "plugin.plugin_definition" key.
    $definition = [];
    $plugin_id = 'foozaar.bazaar';
    $name = 'foo_bar';
    $defaults = [];

    $this->assertNull($this->sut->convert($plugin_id, $definition, $name, $defaults));
  }

}
