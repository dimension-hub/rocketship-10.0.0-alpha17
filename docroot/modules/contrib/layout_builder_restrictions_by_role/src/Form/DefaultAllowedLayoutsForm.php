<?php

namespace Drupal\layout_builder_restrictions_by_role\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\layout_builder_restrictions_by_role\Traits\DefaultLayoutBuilderRestrictionsByRoleHelperTrait;
use Drupal\layout_builder_restrictions_by_role\Traits\LayoutBuilderRestrictionsByRoleHelperTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
class DefaultAllowedLayoutsForm extends ConfigFormBase {

  use PluginHelperTrait;
  use DependencySerializationTrait;
  use DefaultLayoutBuilderRestrictionsByRoleHelperTrait;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $class = parent::create($container);
    $class->sectionStorageManager = $container->get('plugin.manager.layout_builder.section_storage');
    $class->blockManager = $container->get('plugin.manager.block');
    $class->layoutManager = $container->get('plugin.manager.core.layout');
    $class->contextHandler = $container->get('context.handler');
    $class->uuid = $container->get('uuid');
    return $class;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_by_role_allowed_layouts';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'layout_builder_restrictions_by_role.settings',
    ];
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   */
  public function accessCheck(AccountInterface $account) {
    $entity_view_mode_restriction_active = TRUE;
    if ($config = $this->config('layout_builder_restrictions.plugins')->get('plugin_config')) {
      if (isset($config['restriction_by_role']) && $config['restriction_by_role']['enabled'] == FALSE) {
        $entity_view_mode_restriction_active = FALSE;
      }
    }
    return AccessResult::allowedIf($entity_view_mode_restriction_active)
      ->addCacheableDependency($config);
  }

  /**
   * The actual form elements.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['layout']['#tree'] = TRUE;
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

    $third_party_settings = $this->config('layout_builder_restrictions_by_role.settings')->getRawData();

    $form['layout']['layout_builder_restrictions_by_role']['allowed_blocks'] = [
      '#type' => 'details',
      '#title' => $this->t('Blocks available for placement per role (all layouts & regions)'),
      '#parents' => ['layout_builder_restrictions_by_role'],
      '#states' => [
        'disabled' => [
          ':input[name="layout[enabled]"]' => ['checked' => FALSE],
        ],
        'invisible' => [
          ':input[name="layout[enabled]"]' => ['checked' => FALSE],
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
              '#markup' => '<span class="data">' . $this->roleRestrictionsStatusString($role->id(), $static_id) . '</span>',
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
                  'url' => Url::fromRoute("layout_builder_restrictions_by_role.default_allowed_blocks", [
                    'static_id' => $static_id,
                    'role' => $role->id(),
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
          ':input[name="layout[enabled]"]' => ['checked' => FALSE],
        ],
        'invisible' => [
          ':input[name="layout[enabled]"]' => ['checked' => FALSE],
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
          '#markup' => '<div class="restriction-status" id="' . 'restriction-status--' . $plugin_id . '--' . $role->id() . '"><span class="data">' . $this->layoutRoleRestrictionStatusString($role->id(), $plugin_id, $static_id) . '</span></div>',
        ];
        $layout_form['layouts'][$plugin_id]["{$role->id()}"]['operations'] = [
          '#type' => 'dropbutton',
          '#links' => [
            'manage' => [
              'title' => $this->t('Manage allowed blocks'),
              'url' => Url::fromRoute("layout_builder_restrictions_by_role.default_layout_allowed_blocks", [
                'role' => $role->id(),
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

    // Add certain variables as form state temp value for later use.
    $form_state->setTemporaryValue('static_id', $static_id);

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $third_party_settings = $this->config('layout_builder_restrictions_by_role.settings')->getRawData();
    $static_id = $form_state->getTemporaryValue('static_id');
    $store = $this->privateTempStoreFactory()
      ->get('layout_builder_restrictions_by_role');
    $block_restrictions = $store->get($static_id) ? $store->get($static_id) : [];
    $third_party_settings = $this->mergeTemporaryDataIntoThirdPartySettings($third_party_settings, $block_restrictions);
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
    $this->config('layout_builder_restrictions_by_role.settings')->setData($third_party_settings)->save();
    $store->delete($static_id);
  }

}
