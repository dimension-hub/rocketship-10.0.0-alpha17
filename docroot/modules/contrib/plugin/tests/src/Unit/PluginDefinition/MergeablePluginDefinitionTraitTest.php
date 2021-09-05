<?php

namespace Drupal\Tests\plugin\Unit\PluginDefinition;

use Drupal\plugin\PluginDefinition\MergeablePluginDefinitionTrait;
use Drupal\plugin\PluginDefinition\PluginDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use InvalidArgumentException;

/**
 * @coversDefaultClass \Drupal\plugin\PluginDefinition\MergeablePluginDefinitionTrait
 * @group Plugin
 */
class MergeablePluginDefinitionTraitTest extends UnitTestCase {

  /**
   * The class under test.
   *
   * @var \Drupal\plugin\PluginDefinition\MergeablePluginDefinitionTrait|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sut = $this->getMockForTrait(MergeablePluginDefinitionTrait::class);
  }

  /**
   * @covers ::mergeDefaultDefinition
   * @covers ::isDefinitionCompatible
   * @covers ::doMergeDefaultDefinition
   */
  public function testMergeDefaultDefinition() {
    $other_definition = $this->createMock(PluginDefinitionInterface::class);

    $this->sut->expects($this->atLeastOnce())
      ->method('isDefinitionCompatible')
      ->willReturnCallback(function ($value) use ($other_definition) {
        return $value == $other_definition;
      });

    $this->assertSame($this->sut, $this->sut->mergeDefaultDefinition($other_definition));
  }

  /**
   * @covers ::mergeDefaultDefinition
   * @covers ::isDefinitionCompatible
   *
   * @depends testMergeDefaultDefinition
   */
  public function testMergeDefaultDefinitionWithInvalidOtherDefinition() {
    $this->expectException(InvalidArgumentException::class);
    $other_definition = $this->createMock(PluginDefinitionInterface::class);

    $this->sut->expects($this->atLeastOnce())
      ->method('isDefinitionCompatible')
      ->willReturn(FALSE);

    $this->sut->mergeDefaultDefinition($other_definition);
  }

  /**
   * @covers ::mergeOverrideDefinition
   * @covers ::isDefinitionCompatible
   * @covers ::doMergeOverrideDefinition
   */
  public function testMergeOverrideDefinition() {
    $other_definition = $this->createMock(PluginDefinitionInterface::class);

    $this->sut->expects($this->atLeastOnce())
      ->method('isDefinitionCompatible')
      ->willReturnCallback(function ($value) use ($other_definition) {
        return $value == $other_definition;
      });

    $this->assertSame($this->sut, $this->sut->mergeOverrideDefinition($other_definition));
  }

  /**
   * @covers ::mergeOverrideDefinition
   * @covers ::isDefinitionCompatible
   *
   * @depends testMergeOverrideDefinition
   */
  public function testMergeOverrideDefinitionWithInvalidOtherDefinition() {
    $this->expectException(InvalidArgumentException::class);
    $other_definition = $this->createMock(PluginDefinitionInterface::class);

    $this->sut->expects($this->atLeastOnce())
      ->method('isDefinitionCompatible')
      ->willReturn(FALSE);

    $this->sut->mergeOverrideDefinition($other_definition);
  }

}
