<?php

namespace Drupal\field_limiter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\field_formatter\Plugin\Field\FieldFormatter\FieldWrapperBase;

/**
 * Plugin implementation of the 'field_limiter' formatter.
 *
 * @FieldFormatter(
 *   id = "field_limiter",
 *   label = @Translation("Limit the number of rendered items"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class FieldLimiter extends FieldWrapperBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['limit'] = 0;
    $settings['offset'] = 0;
    return $settings;
  }

  /**
   * Returns the cardinality setting of the field instance.
   */
  protected function getCardinality() {
    if ($this->fieldDefinition instanceof FieldDefinitionInterface) {
      return $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    if ($this->getCardinality() == 1) {
      return [];
    }

    $form = parent::settingsForm($form, $form_state);

    $form['offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Skip items'),
      '#default_value' => $this->getSetting('offset'),
      '#required' => TRUE,
      '#min' => 0,
      '#description' => $this->t('Number of items to skip from the beginning.')
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Display items'),
      '#default_value' => $this->getSetting('limit'),
      '#required' => TRUE,
      '#min' => 0,
      '#description' => $this->t('Number of items to display. Set to 0 to display all items.')
    ];

    return $form;
   }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $offset = $this->getSetting('offset') + 1;
    $limit = $this->getSetting('limit');

    if ($limit == 0) {
      $summary[] = $this->t('Showing all values, starting at @offset.', array(
        '@offset' => $offset,
      ));
    }
    else {
      $summary[] = $this->formatPlural($limit, 'Limited to 1 value, starting at @offset.', 'Limited to @count values, starting at @offset.', array(
        '@offset' => $offset,
      ));
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $field_values = $items->getValue();

    $offset = $this->getSetting('offset');
    // To show all elements, expressed by the setting '0', array_slice needs
    // NULL as 3rd argument.
    $limit = $this->getSetting('limit') == 0 ? NULL : $this->getSetting('limit');

    // Let array_slice limit the field values to the ones we want to keep.
    $limited_values = array_slice($field_values, $offset, $limit);
    $items->setValue($limited_values);

    // Generate the output of the field.
    $field_output = $this->getFieldOutput($items, $langcode);

    // Take the element children from the field output and return them.
    $children = Element::children($field_output);
    return array_intersect_key($field_output, array_flip($children));
  }

}
