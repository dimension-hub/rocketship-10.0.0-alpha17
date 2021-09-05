<?php

namespace Drupal\Tests\plugin\Unit\Plugin\Plugin\PluginSelector;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\plugin\Plugin\Plugin\PluginSelector\AdvancedPluginSelectorBase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\plugin\Plugin\Plugin\PluginSelector\AdvancedPluginSelectorBase
 *
 * @group Plugin
 */
class AdvancedPluginSelectorBaseTest extends PluginSelectorBaseTestBase {

  /**
   * The class under test.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\AdvancedPluginSelectorBase|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $sut;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stringTranslation = $this->getStringTranslationStub();

    $this->sut = $this->getMockBuilder(AdvancedPluginSelectorBase::class)
      ->setConstructorArgs(array(
        [],
        $this->pluginId,
        $this->pluginDefinition,
        $this->defaultPluginResolver,
        $this->stringTranslation
      ))
      ->getMockForAbstractClass();
    $this->sut->setSelectablePluginType($this->selectablePluginType);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $map = [
      ['plugin.default_plugin_resolver', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->defaultPluginResolver],
      ['string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation ],
    ];
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    /** @var \Drupal\plugin\Plugin\Plugin\PluginSelector\AdvancedPluginSelectorBase $class */
    $class = get_class($this->sut);
    $plugin = $class::create($container, [], $this->pluginId, $this->pluginDefinition);
    $this->assertInstanceOf(AdvancedPluginSelectorBase::class, $plugin);
  }

  /**
   * @covers ::buildPluginForm
   */
  public function testBuildPluginForm() {
    $form_state = $this->createMock(FormStateInterface::class);

    $plugin_form = [
      '#foo' => $this->randomMachineName(),
    ];

    $plugin = $this->getMockForAbstractClass(AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface::class);
    $plugin->expects($this->once())
      ->method('buildConfigurationForm')
      ->with([], $form_state)
      ->willReturn($plugin_form);


    $method = new \ReflectionMethod($this->sut, 'buildPluginForm');
    $method->setAccessible(TRUE);

    $build = $method->invoke($this->sut, $form_state);
    $this->assertSame('container', $build['#type']);

    $this->sut->setSelectedPlugin($plugin);
    $build = $method->invoke($this->sut, $form_state);
    $this->assertSame('container', $build['#type']);
    $this->assertSame($plugin_form['#foo'], $build['#foo']);
  }

  /**
   * @covers ::buildPluginForm
   */
  public function testBuildPluginFormWithoutPluginForm() {
    $form_state = new FormState();

    $plugin = $this->createMock(PluginInspectionInterface::class);

    $method = new \ReflectionMethod($this->sut, 'buildPluginForm');
    $method->setAccessible(TRUE);

    $build = $method->invoke($this->sut, $form_state);
    $this->assertSame('container', $build['#type']);

    $this->sut->setSelectedPlugin($plugin);
    $build = $method->invoke($this->sut, $form_state);
    $this->assertSame('container', $build['#type']);
  }

  /**
   * @covers ::buildSelectorForm
   * @covers ::setPluginSelector
   */
  public function testBuildSelectorFormWithoutAvailablePlugins() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $this->selectablePluginManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([]);

    $build = $this->sut->buildSelectorForm($form, $form_state);
    unset($build['container']['#plugin_selector_form_state_key']);

    $expected_build = [
      '#cache' => [
        'contexts' => [],
        'tags' => [],
        'max-age' => Cache::PERMANENT,
      ],
      'container' => [
        '#attributes' => [
          'class' => ['plugin-selector-' . Html::getId($this->pluginId)],
        ],
        '#available_plugins' => [],
        '#process' => [
          [
            AdvancedPluginSelectorBase::class,
            'processBuildSelectorForm'
          ]
        ],
        '#tree' => TRUE,
        '#type' => 'container',
      ],
    ];
    $this->assertSame($expected_build, $build);
  }

  /**
   * @covers ::buildSelectorForm
   * @covers ::setPluginSelector
   */
  public function testBuildSelectorFormWithOneAvailablePlugin() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $plugin_id = $this->randomMachineName();
    $plugin = $this->createMock(PluginInspectionInterface::class);

    $plugin_definitions = [
      $plugin_id => [
        'id' => $plugin_id,
      ],
    ];

    $this->selectablePluginManager->expects($this->any())
      ->method('createInstance')
      ->with($plugin_id)
      ->willReturn($plugin);
    $this->selectablePluginManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn($plugin_definitions);

    $build = $this->sut->buildSelectorForm($form, $form_state);
    unset($build['container']['#plugin_selector_form_state_key']);

    $expected_build = [
      '#cache' => [
        'contexts' => [],
        'tags' => [],
        'max-age' => 0,
      ],
      'container' => [
        '#attributes' => [
          'class' => ['plugin-selector-' . Html::getId($this->pluginId)],
        ],
        '#available_plugins' => [$plugin],
        '#process' => [
          [
            AdvancedPluginSelectorBase::class,
            'processBuildSelectorForm'
          ]
        ],
        '#tree' => TRUE,
        '#type' => 'container',
      ],
    ];
    $this->assertSame($expected_build, $build);
  }

  /**
   * Configures a CacheableDependencyInterface mock.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $mock
   *   The mock to configure. It must also implement
   *   \Drupal\Core\Cache\CacheableDependencyInterface.
   * @param string[] $contexts
   * @param int $max_age
   * @Param string[] $tags
   */
  protected function configureMockCacheableDependency(MockObject $mock, array $contexts, $max_age, array $tags) {
    $mock->expects($this->atLeastOnce())
      ->method('getCacheContexts')
      ->willReturn($contexts);
    $mock->expects($this->atLeastOnce())
      ->method('getCacheMaxAge')
      ->willReturn($max_age);
    $mock->expects($this->atLeastOnce())
      ->method('getCacheTags')
      ->willReturn($tags);
  }

  /**
   * @covers ::buildSelectorForm
   * @covers ::setPluginSelector
   */
  public function testBuildSelectorFormWithMultipleAvailablePlugins() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $cache_contexts = [$this->randomMachineName()];
    $cache_tags = [$this->randomMachineName()];

    $plugin_id_a = $this->randomMachineName();
    $plugin_a = $this->createMock(PluginInspectionInterface::class);
    $plugin_id_b = $this->randomMachineName();
    $plugin_b = $this->createMock(AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface::class);
    $this->configureMockCacheableDependency($plugin_b, $cache_contexts, mt_rand(), $cache_tags);

    $plugin_definitions = [
      $plugin_id_a => [
        'id' => $plugin_id_a,
      ],
      $plugin_id_b => [
        'id' => $plugin_id_b,
      ],
    ];

    $map = [
      [$plugin_id_a, [], $plugin_a],
      [$plugin_id_b, [], $plugin_b],
    ];
    $this->selectablePluginManager->expects($this->atLeastOnce())
      ->method('createInstance')
      ->willReturnMap($map);
    $this->selectablePluginManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn($plugin_definitions);

    $build = $this->sut->buildSelectorForm($form, $form_state);
    unset($build['container']['#plugin_selector_form_state_key']);

    $expected_build = [
      '#cache' => [
        'contexts' => $cache_contexts,
        'tags' => $cache_tags,
        'max-age' => 0,
      ],
      'container' => [
        '#attributes' => [
          'class' => ['plugin-selector-' . Html::getId($this->pluginId)],
        ],
        '#available_plugins' => [$plugin_a, $plugin_b],
        '#process' => [
          [
            AdvancedPluginSelectorBase::class,
            'processBuildSelectorForm'
          ]
        ],
        '#tree' => TRUE,
        '#type' => 'container',
      ],
    ];
    $this->assertSame($expected_build, $build);
  }

  /**
   * @covers ::submitSelectorForm
   */
  public function testSubmitSelectorForm() {
    $form = [
      'container' => [
        'plugin_form' => [
          $this->randomMachineName() => [],
        ],
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    $plugin = $this->getMockForAbstractClass(AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface::class);
    $plugin->expects($this->once())
      ->method('submitConfigurationForm')
      ->with($form['container']['plugin_form'], $form_state);

    $this->sut->submitSelectorForm($form, $form_state);
    $this->sut->setSelectedPlugin($plugin);
    $this->sut->submitSelectorForm($form, $form_state);
  }

  /**
   * @covers ::validateSelectorForm
   */
  public function testValidateSelectorForm() {
    $plugin_id_a = $this->randomMachineName();
    $plugin_id_b = $this->randomMachineName();

    $form = [
      'container' => [
        '#parents' => ['foo', 'bar', 'container'],
        'plugin_form' => [
          $this->randomMachineName() => [],
        ],
      ],
    ];

    $plugin_a = $this->getMockForAbstractClass(AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface::class);
    $plugin_a->expects($this->any())
      ->method('getPluginId')
      ->willReturn($plugin_id_a);
    $plugin_b = $this->getMockForAbstractClass(AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface::class);
    $plugin_b->expects($this->never())
      ->method('validateConfigurationForm');
    $plugin_b->expects($this->any())
      ->method('getPluginId')
      ->willReturn($plugin_id_b);

    $map = [
      [$plugin_id_a, [], $plugin_a],
      [$plugin_id_b, [], $plugin_b],
    ];
    $this->selectablePluginManager->expects($this->exactly(2))
      ->method('createInstance')
      ->willReturnMap($map);

    // The plugin is set for the first time. The plugin form must not be
    // validated, as there is no input for it yet.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn([
        'foo' => [
          'bar' => [
            'container' => [
              'select' => [
                'container' => [
                  'plugin_id' => $plugin_id_a,
                ],
              ],
            ],
          ],
        ],
      ]);
    $form_state->expects($this->once())
      ->method('setRebuild');
    $this->sut->validateSelectorForm($form, $form_state);
    $this->assertSame($plugin_a, $this->sut->getSelectedPlugin());

    // The form is validated, but the plugin remains unchanged, and as such
    // should validate its own form as well.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn([
        'foo' => [
          'bar' => [
            'container' => [
              'select' => [
                'container' => [
                  'plugin_id' => $plugin_id_a,
                ],
              ],
            ],
          ],
        ],
      ]);
    $form_state->expects($this->never())
      ->method('setRebuild');
    $plugin_a->expects($this->once())
      ->method('validateConfigurationForm')
      ->with($form['container']['plugin_form'], $form_state);
    $this->sut->validateSelectorForm($form, $form_state);
    $this->assertSame($plugin_a, $this->sut->getSelectedPlugin());

    // The plugin has changed. The plugin form must not be validated, as there
    // is no input for it yet.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn([
        'foo' => [
          'bar' => [
            'container' => [
              'select' => [
                'container' => [
                  'plugin_id' => $plugin_id_b,
                ],
              ],
            ],
          ],
        ],
      ]);
    $form_state->expects($this->once())
      ->method('setRebuild');
    $this->sut->validateSelectorForm($form, $form_state);
    $this->assertSame($plugin_b, $this->sut->getSelectedPlugin());

    // Change the plugin ID back to the original. No new plugin may be
    // instantiated, nor must the plugin form be validated.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn([
        'foo' => [
          'bar' => [
            'container' => [
              'select' => [
                'container' => [
                  'plugin_id' => $plugin_id_a,
                ],
              ],
            ],
          ],
        ],
      ]);
    $form_state->expects($this->once())
      ->method('setRebuild');
    $this->sut->validateSelectorForm($form, $form_state);
    $this->assertSame($plugin_a, $this->sut->getSelectedPlugin());
  }

  /**
   * @covers ::rebuildForm
   */
  public function testRebuildForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setRebuild')
      ->with(TRUE);

    $this->sut->rebuildForm($form, $form_state);
  }

  /**
   * @covers ::buildNoAvailablePlugins
   */
  public function testBuildNoAvailablePlugins() {
    $element = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $label = $this->randomMachineName();

    $this->sut->setLabel($label);

    $expected_build = $element + [
        'select' => [
          'message' => [
            '#markup' => 'There are no available options.',
            '#title' => $label,
            '#type' => 'item',
          ],
          'container' => [
            '#type' => 'container',
            'plugin_id' => [
              '#type' => 'value',
              '#value' => NULL,
            ],
          ],
        ],
      ];
    $this->assertEquals($expected_build, $this->sut->buildNoAvailablePlugins($element, $form_state, $form));
  }

  /**
   * @covers ::buildOneAvailablePlugin
   */
  public function testBuildOneAvailablePlugin() {
    $plugin_id = $this->randomMachineName();

    $plugin_form = [
      '#type' => $this->randomMachineName(),
    ];

    $plugin = $this->getMockForAbstractClass(AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface::class);
    $plugin->expects($this->atLeastOnce())
      ->method('getPluginId')
      ->willReturn($plugin_id);
    $plugin->expects($this->once())
      ->method('buildConfigurationForm')
      ->willReturn($plugin_form);

    $element = [
      '#available_plugins' => [$plugin],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $label = $this->randomMachineName();

    $this->sut->setLabel($label);

    $expected_build = [
      '#available_plugins' => [$plugin],
      'select' => [
        'message' => [
          '#title' => $label,
          '#type' => 'item',
        ],
        'container' => [
          '#type' => 'container',
          'plugin_id' => [
            '#type' => 'value',
            '#value' => $plugin_id,
          ],
        ],
      ],
      'plugin_form' => [
          '#attributes' => [
            'class' => ['plugin-selector-' . Html::getId($this->pluginId) . '-plugin-form'],
          ],
          '#type' => 'container',
        ] + $plugin_form,
    ];
    $build = $this->sut->buildOneAvailablePlugin($element, $form_state, $form);
    unset($build['plugin_form']['#id']);
    $this->assertSame($expected_build, $build);
  }

  /**
   * @covers ::buildMultipleAvailablePlugins
   */
  public function testbuildMultipleAvailablePlugins() {
    $plugin = $this->createMock(PluginInspectionInterface::class);

    $element = [
      '#available_plugins' => [$plugin],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $plugin_form = [
      '#type' => $this->randomMachineName(),
    ];

    $selector = [
      '#type' => $this->randomMachineName(),
    ];

    /** @var \Drupal\plugin\Plugin\Plugin\PluginSelector\AdvancedPluginSelectorBase|\PHPUnit_Framework_MockObject_MockObject $plugin_selector */
    $plugin_selector = $this->getMockBuilder(AdvancedPluginSelectorBase::class)
      ->setMethods(['buildPluginForm', 'buildSelector'])
      ->setConstructorArgs([
        [],
        $this->pluginId,
        $this->pluginDefinition,
        $this->defaultPluginResolver,
        $this->stringTranslation
      ])
      ->getMockForAbstractClass();
    $plugin_selector->setSelectablePluginType($this->selectablePluginType);
    $plugin_selector->expects($this->once())
      ->method('buildPluginForm')
      ->with($form_state)
      ->willReturn($plugin_form);
    $plugin_selector->expects($this->once())
      ->method('buildSelector')
      ->with($element, $form_state, [$plugin])
      ->willReturn($selector);
    $plugin_selector->setSelectedPlugin($plugin);

    $expected_build = [
      '#available_plugins' => [$plugin],
      'select' => $selector,
      'plugin_form' => $plugin_form,
    ];
    $this->assertEquals($expected_build, $plugin_selector->buildMultipleAvailablePlugins($element, $form_state, $form));
  }

  /**
   * @covers ::setSelectedPlugin
   * @covers ::getSelectedPlugin
   */
  public function testGetPlugin() {
    $plugin = $this->createMock(PluginInspectionInterface::class);
    $this->assertSame($this->sut, $this->sut->setSelectedPlugin($plugin));
    $this->assertSame($plugin, $this->sut->getSelectedPlugin());
  }

  /**
   * @covers ::buildSelector
   */
  public function testBuildSelector() {
    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->willReturnArgument(0);

    $method = new \ReflectionMethod($this->sut, 'buildSelector');
    $method->setAccessible(TRUE);

    $plugin_id = $this->randomMachineName();
    $plugin_label = $this->randomMachineName();
    $plugin = $this->createMock(PluginInspectionInterface::class);
    $plugin->expects($this->any())
      ->method('getPluginId')
      ->willReturn($plugin_id);

    $this->sut->setSelectedPlugin($plugin);

    $element = [
      '#parents' => ['foo', 'bar'],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $available_plugins = [$plugin];

    $expected_build_change = [
      '#ajax' => [
        'callback' => [
          AdvancedPluginSelectorBase::class,
          'ajaxRebuildForm'
        ],
      ],
      '#attributes' => [
        'class' => ['js-hide']
      ],
      '#limit_validation_errors' => [
        [
          'foo',
          'bar',
          'select',
          'plugin_id'
        ]
      ],
      '#name' => 'foo__bar__select__container__change',
      '#submit' => [[AdvancedPluginSelectorBase::class, 'rebuildForm']],
      '#type' => 'submit',
      '#value' => 'Choose',
    ];
    $build = $method->invokeArgs($this->sut, [
      $element,
      $form_state,
      $available_plugins
    ]);
    $this->assertArrayHasKey('plugin_id', $build['container']);
    $this->assertEquals($expected_build_change, $build['container']['change']);
    $this->assertSame('container', $build['container']['#type']);
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration() {
    $this->assertIsArray($this->sut->defaultConfiguration());
    $this->assertIsArray($this->sut->defaultConfiguration());
  }

  /**
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationForm() {
    $form = [
      'plugin' => [],
    ];
    $form_state = new FormState();
    $configuration_form = $form['plugin'];
    $configuration_form_state = SubformState::createForSubform($configuration_form, $form, $form_state);
    $this->assertIsArray($this->sut->buildConfigurationForm($configuration_form, $configuration_form_state));
  }

  /**
   * @covers ::getSelectorVisibilityForSingleAvailability
   * @covers ::setSelectorVisibilityForSingleAvailability
   */
  public function testGetSelectorVisibilityForSingleAvailability() {
    $this->assertFalse($this->sut->getSelectorVisibilityForSingleAvailability());
    $this->sut->setSelectorVisibilityForSingleAvailability(TRUE);
    $this->assertTrue($this->sut->getSelectorVisibilityForSingleAvailability());
  }

}


/**
 * Provides a plugin that provides a form and cacheability metadata.
 */
interface AdvancedPluginSelectorBaseUnitTestPluginFormPluginInterface extends PluginInspectionInterface, PluginFormInterface, CacheableDependencyInterface {
}
