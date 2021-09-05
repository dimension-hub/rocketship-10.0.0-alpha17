<?php

namespace Drupal\layout_builder_restrictions_by_role\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Drupal\layout_builder_restrictions_by_role\Traits\LayoutBuilderRestrictionsByRoleHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides form for designating allowed blocks.
 */
class AllowedBlocksForm extends FormBase {

  use PluginHelperTrait;
  use LayoutBuilderRestrictionsByRoleHelperTrait;

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
   * @var array
   */
  protected $thirdPartySettings;

  /**
   * @var mixed|null
   */
  protected $role;

  /**
   * @var mixed|null
   */
  protected $entityViewDisplayId;

  /**
   * @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface
   */
  protected $display;

  /**
   * The ModalFormController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack that controls the lifecycle of requests.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Manages entity type plugin definitions.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store_factory
   *   Creates a private temporary storage for a collection.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Turns a render array into a HTML string.
   */
  public function __construct(RequestStack $request_stack, LayoutPluginManagerInterface $layout_manager, EntityTypeManager $entity_type_manager, PrivateTempStoreFactory $private_temp_store_factory, MessengerInterface $messenger, Renderer $renderer) {
    $this->requestStack = $request_stack;
    $this->layoutManager = $layout_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStoreFactory = $private_temp_store_factory;
    $this->messenger = $messenger;
    $this->renderer = $renderer;

    // Build data for current form.
    $current_request = $this->requestStack->getCurrentRequest();
    $this->role = $current_request->query->get('role');
    $this->entityViewDisplayId = $current_request->query->get('entity_view_display_id');
    $this->display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($this->entityViewDisplayId);
    $this->staticId = $current_request->query->get('static_id');
    $this->regionId = $current_request->query->get('region_id');
    $this->thirdPartySettings = $this->display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_per_role', []);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('messenger'),
      $container->get('renderer')
    );
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
    $settings = $this->getSettings($this->entityViewDisplayId, $this->staticId);

    foreach ($this->getBlockDefinitions($this->display) as $category => $data) {
      $title = $data['label'];
      if (!empty($data['translated_label'])) {
        $title = $data['translated_label'];
      }
      $category_form = [
        '#type' => 'fieldset',
        '#title' => $title,
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
        'url' => Url::fromRoute("layout_builder_restrictions_by_role.allowed_blocks", [
          'static_id' => $this->staticId,
          'entity_view_display_id' => $this->entityViewDisplayId,
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

    $region_status = $this->roleRestrictionsStatusString($this->role, $this->staticId, NULL);
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

}
