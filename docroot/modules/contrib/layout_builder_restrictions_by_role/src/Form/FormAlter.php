<?php

namespace Drupal\layout_builder_restrictions_by_role\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\layout_builder_restrictions_by_role\Traits\LayoutBuilderRestrictionsByRoleHelperTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
class FormAlter implements ContainerInjectionInterface {

  use PluginHelperTrait;
  use DependencySerializationTrait;
  use LayoutBuilderRestrictionsByRoleHelperTrait;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * FormAlter constructor.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   A service for generating UUIDs.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, BlockManagerInterface $block_manager, LayoutPluginManagerInterface $layout_manager, ContextHandlerInterface $context_handler, UuidInterface $uuid) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->blockManager = $block_manager;
    $this->layoutManager = $layout_manager;
    $this->contextHandler = $context_handler;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('plugin.manager.block'),
      $container->get('plugin.manager.core.layout'),
      $container->get('context.handler'),
      $container->get('uuid'),
    );
  }

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayForm(&$form, FormStateInterface $form_state, $form_id) {
    // Create a unique ID for this form build and store it in a hidden
    // element on the rendered form. This will be used to retrieve data
    // from tempStore.
    $user_input = $form_state->getUserInput();
    if (!isset($user_input['static_id'])) {
      $static_id = $this->uuid->generate();
    }
    else {
      $static_id = $user_input['static_id'];
    }
    $form['static_id'] = [
      '#type' => 'hidden',
      '#value' => $static_id,
    ];

    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if (!$is_enabled) {
      return $form;
    }

    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = Role::loadMultiple();
    foreach ($roles as $idx => $role) {
      if ($role->id() === RoleInterface::ANONYMOUS_ID) {
        unset($roles[$idx]);
      }
      if ($role->id() === RoleInterface::AUTHENTICATED_ID) {
        unset($roles[$idx]);
      }
    }
    if (empty($roles)) {
      return $form;
    }

    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', []);

    $form['layout']['layout_builder_restrictions_by_role']['override_defaults'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override default "Per role" settings'),
      '#description' => $this->t('Ignore default "Per role" settings for this view mode, instead use the settings saved here.'),
      '#default_value' => $third_party_settings['override_defaults'] ?? FALSE,
      '#states' => [
        'disabled' => [
          ':input[name="layout[enabled]"]' => ['checked' => FALSE],
        ],
        'invisible' => [
          ':input[name="layout[enabled]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['layout']['layout_builder_restrictions_by_role']['allowed_blocks'] = [
      '#type' => 'details',
      '#title' => $this->t('Blocks available for placement per role (all layouts & regions)'),
      '#parents' => ['layout_builder_restrictions_by_role'],
      '#states' => [
        'disabled' => [
          ':input[name="layout[layout_builder_restrictions_by_role][override_defaults]"]' => ['checked' => FALSE],
        ],
        'invisible' => [
          ':input[name="layout[layout_builder_restrictions_by_role][override_defaults]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['layout']['layout_builder_restrictions_by_role']['allowed_blocks']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Role'),
        $this->t('Status'),
        $this->t('Operations'),
      ],
    ];

    foreach ($roles as $role) {
      $form['layout']['layout_builder_restrictions_by_role']['allowed_blocks']['table']['#rows'][$role->id()] = [
        'data' => [
          'region_label' => [
            'class' => [
              'role-label',
            ],
            'data' => [
              '#markup' => $role->label(),
            ],
          ],
          'status' => [
            'class' => [
              'restriction-status',
            ],
            'id' => 'restriction-status--' . $role->id(),
            'data' => [
              '#markup' => '<span class="data">' . $this->roleRestrictionsStatusString($role->id(), $static_id, $display->get('id')) . '</span>',
            ],
          ],
          'operations' => [
            'class' => [
              'operations',
            ],
            'data' => [
              '#type' => 'dropbutton',
              '#links' => [
                'manage' => [
                  'title' => $this->t('Manage allowed blocks'),
                  'url' => Url::fromRoute("layout_builder_restrictions_by_role.allowed_blocks", [
                    'static_id' => $static_id,
                    'role' => $role->id(),
                    'entity_view_display_id' => $display->get('id'),
                  ]),
                  'attributes' => [
                    'class' => [
                      'use-ajax',
                    ],
                    'data-dialog-type' => 'modal',
                    'data-dialog-options' => Json::encode(['width' => 800]),
                  ],
                ],
              ],
            ],
          ],
        ],
      ];
    }

    // Layout settings.
    $layout_form = [
      '#type' => 'details',
      '#title' => $this->t('Layouts available for sections per role'),
      '#parents' => ['layout_builder_restrictions_by_role', 'allowed_layouts'],
      '#states' => [
        'disabled' => [
          ':input[name="layout[layout_builder_restrictions_by_role][override_defaults]"]' => ['checked' => FALSE],
        ],
        'invisible' => [
          ':input[name="layout[layout_builder_restrictions_by_role][override_defaults]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $layout_form['layout_restriction'] = [
      '#type' => 'radios',
      '#options' => [
        "all" => $this->t('Allow all existing & new layouts.'),
        "restricted" => $this->t('Allow only specific layouts:'),
      ],
      '#default_value' => $third_party_settings['layout_restriction'] ?? 'all',
    ];
    $layout_form['layouts'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          ':input[name="layout_builder_restrictions_by_role[allowed_layouts][layout_restriction]"]' => ['value' => "all"],
        ],
      ],
    ];
    $definitions = $this->getLayoutDefinitions();
    foreach ($definitions as $plugin_id => $definition) {

      $layout_form['layouts'][$plugin_id] = [
        '#type' => 'table',
        '#header' => [
          $definition->getLabel() . ' (' . $plugin_id . ')',
          $this->t('Status'),
          $this->t('Operations'),
        ],
        '#tableselect' => TRUE,
        '#default_value' => $third_party_settings['allowed_layouts'][$plugin_id] ?? [],
      ];

      foreach ($roles as $role) {
        $layout_form['layouts'][$plugin_id]["{$role->id()}"]['label'] = [
          '#plain_text' => $role->label(),
        ];
        $layout_form['layouts'][$plugin_id]["{$role->id()}"]['status'] = [
          '#markup' => '<div class="restriction-status" id="' . 'restriction-status--' . $plugin_id . '--' . $role->id() . '"><span class="data">' . $this->layoutRoleRestrictionStatusString($role->id(), $plugin_id, $static_id, $display->get('id')) . '</span></div>',
        ];
        $layout_form['layouts'][$plugin_id]["{$role->id()}"]['operations'] = [
          '#type' => 'dropbutton',
          '#links' => [
            'manage' => [
              'title' => $this->t('Manage allowed blocks'),
              'url' => Url::fromRoute("layout_builder_restrictions_by_role.layout_allowed_blocks", [
                'role' => $role->id(),
                'entity_view_display_id' => $display->get('id'),
                'layout_plugin' => $plugin_id,
                'static_id' => $static_id,
              ]),
              'attributes' => [
                'class' => [
                  'use-ajax',
                ],
                'data-dialog-type' => 'modal',
                'data-dialog-options' => Json::encode(['width' => 800]),
              ],
            ],
          ],
        ];
      }
    }
    $form['layout']['layout_builder_restrictions_by_role']['allowed_layouts'] = $layout_form;

    $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];

    // Add certain variables as form state temp value for later use.
    $form_state->setTemporaryValue('static_id', $static_id);
  }

  /**
   * Save allowed blocks & layouts for the given entity view mode.
   *
   * @param $entity_type_id
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function entityFormEntityBuild($entity_type_id, LayoutEntityDisplayInterface $display, &$form, FormStateInterface &$form_state) {
    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role') ?? [];
    $static_id = $form_state->getTemporaryValue('static_id');
    $store = $this->privateTempStoreFactory()
      ->get('layout_builder_restrictions_by_role');
    $block_restrictions = $store->get($static_id) ? $store->get($static_id) : [];
    $third_party_settings = $this->mergeTemporaryDataIntoThirdPartySettings($third_party_settings, $block_restrictions);
    $third_party_settings['override_defaults'] = $form_state->getValue([
      'layout',
      'layout_builder_restrictions_by_role',
      'override_defaults',
    ]);
    $third_party_settings['layout_restriction'] = $form_state->getValue([
      'layout_builder_restrictions_by_role',
      'allowed_layouts',
      'layout_restriction',
    ]);
    $third_party_settings['allowed_layouts'] = $form_state->getValue([
      'layout_builder_restrictions_by_role',
      'allowed_layouts',
      'layouts',
    ]);
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', $third_party_settings);
    $store->delete($static_id);
  }

}
