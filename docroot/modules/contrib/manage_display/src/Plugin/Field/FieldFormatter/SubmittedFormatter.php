<?php

namespace Drupal\manage_display\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Plugin\Field\FieldFormatter\AuthorFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field formatter for entity titles.
 *
 * @FieldFormatter(
 *   id = "submitted",
 *   label = @Translation("Submitted"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class SubmittedFormatter extends AuthorFormatter {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a EntityReferenceEntityFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();
    $options['user_picture'] = '';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $view_modes = ['' => $this->t('- None -')];
    $view_modes += $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));

    $form['user_picture'] = [
      '#type' => 'select',
      '#options' => $view_modes,
      '#title' => $this->t('View mode for user picture'),
      '#default_value' => $this->getSetting('user_picture'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if ($view_mode = $this->getSetting('user_picture')) {
      $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));
      $summary[] = $this->t('User picture view mode: @mode', ['@mode' => $view_modes[$view_mode] ?? $view_mode]);
    }
    else {
      $summary[] = $this->t('No user picture');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as &$element) {
      $element['#theme'] = 'submitted';
      $element['#date'] = $items->getEntity()->get('created')->value ?? NULL;
      if ($view_mode = $this->getSetting('user_picture')) {
        $element['#user_picture'] = \Drupal::entityTypeManager()->getViewBuilder('user')->view($element['#account'], $view_mode, $langcode);
      }
    }

    return $elements;
  }

}
