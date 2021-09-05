<?php

namespace Drupal\Tests\plugin\Unit\PluginDefinition;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\plugin\PluginDefinition\PluginContextDefinitionTrait;
use Drupal\Tests\UnitTestCase;
use InvalidArgumentException;

/**
 * @coversDefaultClass \Drupal\plugin\PluginDefinition\PluginContextDefinitionTrait
 *
 * @group Plugin
 */
class PluginContextDefinitionTraitTest extends UnitTestCase {

  /**
   * The class under test.
   *
   * @var \Drupal\plugin\PluginDefinition\PluginContextDefinitionTrait
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sut = $this->getMockForTrait(PluginContextDefinitionTrait::class);
  }

  /**
   * @covers ::setContextDefinitions
   * @covers ::getContextDefinitions
   */
  public function testGetContextDefinitions() {
    $context_definition_name_a = $this->randomMachineName();
    $context_definition_a = $this->createMock(ContextDefinitionInterface::class);
    $context_definition_name_b = $this->randomMachineName();
    $context_definition_b = $this->createMock(ContextDefinitionInterface::class);

    $context_definitions = [
      $context_definition_name_a => $context_definition_a,
      $context_definition_name_b => $context_definition_b,
    ];

    $this->assertSame($this->sut, $this->sut->setContextDefinitions($context_definitions));
    $this->assertSame($context_definitions, $this->sut->getContextDefinitions());
  }

  /**
   * @covers ::setContextDefinitions
   *
   * @depends testGetContextDefinitions
   */
  public function testSetContextDefinitionsWithInvalidDefinition() {
    $this->expectException(InvalidArgumentException::class);
    $context_definitions = [
      $this->randomMachineName() => new \stdClass(),
    ];

    $this->sut->setContextDefinitions($context_definitions);
  }

  /**
   * @covers ::setContextDefinition
   * @covers ::getContextDefinition
   * @covers ::hasContextDefinition
   */
  public function testGetContextDefinition() {
    $name = $this->randomMachineName();
    $context_definition = $this->createMock(ContextDefinitionInterface::class);

    $this->assertSame($this->sut, $this->sut->setContextDefinition($name, $context_definition));
    $this->assertSame($context_definition, $this->sut->getContextDefinition($name));
    $this->assertTrue($this->sut->hasContextDefinition($name));
  }

  /**
   * @covers ::getContextDefinition
   * @covers ::hasContextDefinition
   *
   * @depends testGetContextDefinition
   */
  public function testGetContextDefinitionWithNonExistentDefinition() {
    $this->expectException(InvalidArgumentException::class);
    $name = $this->randomMachineName();

    $this->assertFalse($this->sut->hasContextDefinition($name));
    $this->sut->getContextDefinition($name);
  }

}
