<?php

namespace Drupal\layout_builder_restrictions_by_role\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\layout_builder_restrictions_by_role\Traits\DefaultLayoutBuilderRestrictionsByRoleHelperTrait;
use Drupal\layout_builder_restrictions_by_role\Traits\LayoutBuilderRestrictionsByRoleHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides form for designating allowed blocks.
 */
class DefaultAllowedBlocksForm extends ConfigFormBase {

  use PluginHelperTrait;
  use DefaultLayoutBuilderRestrictionsByRoleHelperTrait;

  /**
   * Request stack that controls the lifecycle of requests.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * Manages entity type plugin definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates a private temporary storage for a collection.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Turns a render array into a HTML string.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The machine name of the region.
   *
   * @var string
   */
  protected $regionId;

  /**
   * The machine name of the static id.
   *
   * @var string
   */
  protected $staticId;

  /**
   * @var mixed|null
   */
  protected $role;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $class = parent::create($container);
    $class->requestStack = $container->get('request_stack');
    $class->layoutManager = $container->get('plugin.manager.core.layout');
    $class->entityTypeManager = $container->get('entity_type.manager');
    $class->privateTempStoreFactory = $container->get('tempstore.private');
    $class->messenger = $container->get('messenger');
    $class->renderer = $container->get('renderer');

    // Build data for current form.
    $current_request = $class->requestStack->getCurrentRequest();
    $class->role = $current_request->query->get('role');
    $class->staticId = $current_request->query->get('static_id');
    $class->regionId = $current_request->query->get('region_id');

    return $class;
  }

  /**
   * @return array|string[]
   */
  protected function getEditableConfigNames() {
    return [
      'layout_builder_restrictions_by_role.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_by_role_allowed_blocks';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings($this->staticId);

    foreach ($this->getBlockDefinitions() as $category => $data) {
      $title = $data['label'];
      if (!empty($data['translated_label'])) {
        $title = $data['translated_label'];
      }
      $category_form = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => $this->getCategoryBehavior($this->role, $category, $settings) !== 'all',
      ];
      $category_form['restriction_behavior'] = [
        '#type' => 'radios',
        '#options' => [
          "all" => $this->t('Allow all existing & new %category blocks.', ['%category' => $data['label']]),
          "whitelisted" => $this->t('Allow specific %category blocks:', ['%category' => $data['label']]),
          "blacklisted" => $this->t('Restrict specific %category blocks:', ['%category' => $data['label']]),
        ],
        '#parents' => [
          'allowed_blocks',
          $category,
          'restriction',
        ],
      ];
      $category_form['restriction_behavior']['#default_value'] = $this->getCategoryBehavior($this->role, $category, $settings);
      $category_form['allowed_blocks'] = [
        '#type' => 'container',
        '#states' => [
          'invisible' => [
            ':input[name="allowed_blocks[' . $category . '][restriction]"]' => ['value' => "all"],
          ],
        ],
      ];
      foreach ($data['definitions'] as $block_id => $block) {
        if ($category == 'Content fields') {
          $block['admin_label'] .= ' (' . $block_id . ')';
        }
        $category_form['allowed_blocks'][$block_id] = [
          '#type' => 'checkbox',
          '#title' => $block['admin_label'],
          '#default_value' => $this->getBlockDefault($this->role, $block_id, $category, $settings),
          '#parents' => [
            'allowed_blocks',
            $category,
            'allowed_blocks',
            $block_id,
          ],
        ];
      }

      if ($category == 'Custom blocks' || $category == 'Custom block types') {
        $category_form['description'] = [
          '#type' => 'container',
          '#children' => $this->t('<p>In the event both <em>Custom Block Types</em> and <em>Custom Blocks</em> restrictions are enabled, <em>Custom Block Types</em> restrictions are disregarded.</p>'),
          '#states' => [
            'visible' => [
              ':input[name="allowed_blocks[' . $category . '][restriction]"]' => ['value' => "restricted"],
            ],
          ],
        ];
      }
      $form['allowed_blocks'][$category] = $category_form;
    }

    $form['actions']['submit'] = $this->createAjaxSubmit($form, $form_state);

    return $form;
  }

  /**
   * Returns array for a submit button with AJAX.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure for the submit button.
   */
  protected function createAjaxSubmit($form, FormStateInterface $form_state) {
    return [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
        'url' => Url::fromRoute("layout_builder_restrictions_by_role.default_allowed_blocks", [
          'static_id' => $this->staticId,
          'role' => $this->role,
        ]),
        'options' => [
          'query' => [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];
  }

  /**
   * Callback function for AJAX form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $this->saveDataToTempStore($form_state);
    return $this->createAjaxResponse($form_state);
  }

  /**
   * Save data to temporary store.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function saveDataToTempStore(FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $static_id = $this->staticId;
    $role = $this->role;
    $categories = $values['allowed_blocks'];

    $tempstore = $this->privateTempStoreFactory;
    $store = $tempstore->get('layout_builder_restrictions_by_role');
    $block_restrictions = $store->get($static_id) ? $store->get($static_id) : [];
    $restrictions = $block_restrictions['__blocks'] ?? [];

    if (!empty($categories)) {
      foreach ($categories as $category => $category_setting) {
        $restriction_type = $category_setting['restriction'];
        $restrictions[$role][$category]['restriction_type'] = $restriction_type;
        if (in_array($restriction_type, ['whitelisted', 'blacklisted'])) {
          foreach ($category_setting['allowed_blocks'] as $block_id => $block_setting) {
            if ($block_setting == '1') {
              // Include only checked blocks.
              $restrictions[$role][$category]['restrictions'][$block_id] = $block_setting;
            }
            elseif (isset($restrictions[$role][$category]['restrictions'][$block_id])) {
              unset($restrictions[$role][$category]['restrictions'][$block_id]);
            }
          }
        }
        elseif (isset($restrictions[$role][$category])) {
          unset($restrictions[$role][$category]);
        }
      }
    }

    $block_restrictions['__blocks'] = $restrictions;
    // Write settings to tempStore.
    $store->set($static_id, $block_restrictions);
  }

  /**
   * Create AJAX response to return.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  protected function createAjaxResponse(FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $command = new CloseModalDialogCommand();
    $response->addCommand($command);
    $this->messenger->addWarning($this->t('There is unsaved Layout Builder Restrictions configuration.'));
    $status_messages = ['#type' => 'status_messages'];
    $messages = $this->renderer->renderRoot($status_messages);
    $messages = '<div id="layout-builder-restrictions-messages">' . $messages . '</div>';
    if (!empty($messages)) {
      $response->addCommand(new ReplaceCommand('#layout-builder-restrictions-messages', $messages));
    }

    $region_status = $this->roleRestrictionsStatusString($this->role, $this->staticId);
    $response->addCommand(new ReplaceCommand('#restriction-status--' . $this->role . ' .data', '<span class="data">' . $region_status . '</span>'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Business logic to set category to 'all', 'whitelisted' or 'blacklisted'.
   *
   * @param string $category
   *   The block's category.
   * @param mixed $settings
   *   The stored data merged with the temp data stored between AJAX calls.
   *
   * @return string
   *   The value 'all', 'whitelisted' or 'blacklisted'.
   */
  protected function getCategoryBehavior($role, $category, $settings) {
    if (!empty($settings['__blocks'][$role][$category]['restriction_type'])) {
      return $settings['__blocks'][$role][$category]['restriction_type'];
    }
    return 'all';
  }

  /**
   * Business logic to set category to 'all', 'whitelisted' or 'blacklisted'.
   *
   * @param string $block_id
   *   The Drupal block ID.
   * @param string $category
   *   The block's category.
   * @param mixed $settings
   *   The stored data merged with the temp data stored between AJAX calls.
   *
   * @return bool
   *   Whether or not the block is stored in the restriction type.
   */
  protected function getBlockDefault($role, $block_id, $category, $settings) {
    if (!isset($settings['__blocks'][$role])) {
      // No restrictions.
      return FALSE;
    }
    foreach ($settings['__blocks'][$role] as $_category => $_settings) {
      if ($_category !== $category) {
        continue;
      }
      if (!isset($_settings['restriction_type'])) {
        // No restrictions here.
        return FALSE;
      }
      if ($_settings['restriction_type'] === 'all') {
        // No restrictions here.
        return FALSE;
      }
      // Got a restriction.
      return (!empty($_settings['restrictions'][$block_id]));
    }
    return FALSE;
  }

  /**
   * Gets block definitions appropriate for an entity display.
   *
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display
   *   The entity display being edited.
   *
   * @return array[]
   *   Keys are category names, and values are arrays of which the keys are
   *   plugin IDs and the values are plugin definitions.
   */
  protected function getBlockDefinitions() {
    // Do not use the plugin filterer here, but still filter by contexts.
    $definitions = $this->blockManager()->getDefinitions();

    // Create a list of block_content IDs for later filtering.
    $custom_blocks = [];
    foreach ($definitions as $key => $definition) {
      if ($definition['provider'] == 'block_content') {
        $custom_blocks[] = $key;
      }
    }

    $grouped_definitions = $this->getDefinitionsByUntranslatedCategory($definitions);
    // Create a new category of block_content blocks that meet the context.
    foreach ($grouped_definitions as $category => $data) {
      if (empty($data['definitions'])) {
        unset($grouped_definitions[$category]);
      }
      // Ensure all block_content definitions are included in the
      // 'Custom blocks' category.
      foreach ($data['definitions'] as $key => $definition) {
        if (in_array($key, $custom_blocks)) {
          if (!isset($grouped_definitions['Custom blocks'])) {
            $grouped_definitions['Custom blocks'] = [
              'label' => 'Custom blocks',
              'data' => [],
            ];
          }
          // Remove this block_content from its previous category so
          // that it is defined only in one place.
          unset($grouped_definitions[$category]['definitions'][$key]);
          $grouped_definitions['Custom blocks']['definitions'][$key] = $definition;
        }
      }
    }

    // Generate a list of custom block types under the
    // 'Custom block types' namespace.
    $custom_block_bundles = $this->entityTypeBundleInfo()->getBundleInfo('block_content');
    if ($custom_block_bundles) {
      $grouped_definitions['Custom block types'] = [
        'label' => 'Custom block types',
        'definitions' => [],
      ];
      foreach ($custom_block_bundles as $machine_name => $value) {
        $grouped_definitions['Custom block types']['definitions'][$machine_name] = [
          'admin_label' => $value['label'],
          'category' => $this->t('Custom block types'),
        ];
      }
    }
    ksort($grouped_definitions);

    return $grouped_definitions;
  }

}
