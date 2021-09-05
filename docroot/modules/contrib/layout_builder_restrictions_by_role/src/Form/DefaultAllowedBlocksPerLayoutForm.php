<?php

namespace Drupal\layout_builder_restrictions_by_role\Form;

use Drupal\Component\Utility\Html;
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
 * Provides form for designating allowed blocks for a given layout.
 */
class DefaultAllowedBlocksPerLayoutForm extends DefaultAllowedBlocksForm {

  /**
   * The machine name of the layout plugin.
   *
   * @var string
   */
  protected $layoutPluginId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $class = parent::create($container);
    // Build data for current form.
    $current_request = $class->requestStack->getCurrentRequest();
    $class->layoutPluginId = $current_request->query->get('layout_plugin');
    return $class;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_by_role_allowed_layout_blocks';
  }

  /**
   * {@inheritdoc}
   */
  protected function createAjaxSubmit($form, FormStateInterface $form_state) {
    return $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
        'url' => Url::fromRoute("layout_builder_restrictions_by_role.default_layout_allowed_blocks", [
          'static_id' => $this->staticId,
          'role' => $this->role,
          'layout_plugin' => $this->layoutPluginId,
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
   * {@inheritdoc}
   */
  protected function saveDataToTempStore(FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $static_id = $this->staticId;
    $role = $this->role;
    $categories = $values['allowed_blocks'];

    $tempstore = $this->privateTempStoreFactory;
    $store = $tempstore->get('layout_builder_restrictions_by_role');
    $block_restrictions = $store->get($static_id) ? $store->get($static_id) : [];

    if (!empty($categories)) {
      foreach ($categories as $category => $category_setting) {
        $restriction_type = $category_setting['restriction'];
        $block_restrictions['__layouts'][$this->layoutPluginId][$role][$category]['restriction_type'] = $restriction_type;
        if (in_array($restriction_type, ['whitelisted', 'blacklisted'])) {
          foreach ($category_setting['allowed_blocks'] as $block_id => $block_setting) {
            if ($block_setting == '1') {
              // Include only checked blocks.
              $block_restrictions['__layouts'][$this->layoutPluginId][$role][$category]['restrictions'][$block_id] = $block_setting;
            }
            elseif (isset($block_restrictions['__layouts'][$this->layoutPluginId][$role][$category]['restrictions'][$block_id])) {
              unset($block_restrictions['__layouts'][$this->layoutPluginId][$role][$category]['restrictions'][$block_id]);
            }
          }
        }
        else {
          unset($block_restrictions['__layouts'][$this->layoutPluginId][$role][$category]);
        }
      }
    }

    // Write settings to tempStore.
    $store->set($static_id, $block_restrictions);
  }

  /**
   * {@inheritdoc}
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
    $region_status = $this->layoutRoleRestrictionStatusString($this->role, $this->layoutPluginId, $this->staticId);
    $response->addCommand(new ReplaceCommand('#restriction-status--' . $this->layoutPluginId . '--' . $this->role . ' .data', '<span class="data">' . $region_status . '</span>'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCategoryBehavior($role, $category, $settings) {
    if (!empty($settings['__layouts'][$this->layoutPluginId][$role][$category]['restriction_type'])) {
      return $settings['__layouts'][$this->layoutPluginId][$role][$category]['restriction_type'];
    }
    return 'all';
  }

  /**
   * {@inheritdoc}
   */
  protected function getBlockDefault($role, $block_id, $category, $settings) {
    if (!isset($settings['__layouts'][$this->layoutPluginId][$role])) {
      // No restrictions.
      return FALSE;
    }
    foreach ($settings['__layouts'][$this->layoutPluginId][$role] as $_category => $_settings) {
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
