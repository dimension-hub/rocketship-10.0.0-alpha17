<?php

namespace Drupal\office_hours\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\office_hours\OfficeHoursDateHelper;

/**
 * Plugin implementation of the 'office_hours_default' widget.
 *
 * @FieldWidget(
 *   id = "office_hours_default",
 *   label = @Translation("Office hours (week)"),
 *   field_types = {
 *     "office_hours",
 *   },
 *   multiple_values = "FALSE",
 * )
 */
class OfficeHoursDefaultWidget extends OfficeHoursWidgetBase {

  /**
   * Special handling to create form elements for multiple values.
   *
   * Removed the added generic features for multiple fields:
   * - Number of widgets;
   * - AHAH 'add more' button;
   * - Table display and drag-n-drop value reordering.
   * N.B. This is never called with Annotation: multiple_values = "FALSE".
   *
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()
      ->getCardinality();
    if ($field_cardinality == FieldStorageDefinitionInterface  ::CARDINALITY_UNLIMITED) {
      $this->fieldDefinition->getFieldStorageDefinition()
        ->setCardinality(7 * $this->getFieldSetting('cardinality_per_day'));
    }

    $elements = parent::formMultipleElements($items, $form, $form_state);

    // Remove the 'drag-n-drop reordering' element.
    $elements['#cardinality_multiple'] = FALSE;
    // Remove the little 'Weight for row n' box.
    unset($elements[0]['_weight']);

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // In D8, we have a (deliberate) anomaly in the widget.
    // We prepare 1 widget for the whole week, but the field has unlimited cardinality.
    // So with $delta = 0, we already show ALL values.
    if ($delta > 0) {
      return [];
    }

    // Create an indexed two level array of time slots.
    // - First level are day numbers.
    // - Second level contains field values arranged by $day_delta.
    $indexed_items = array_fill_keys(range(0, 6), []);
    foreach ($items as $index => $item) {
      $value_of_item = $item->getValue();
      if ($item && !empty($value_of_item)) {
        $indexed_items[(int) $value_of_item['day']][] = $item;
      }
    }

    // Build elements, sorted by first_day_of_week.
    $elements = [];
    $days = OfficeHoursDateHelper::weekDaysOrdered(range(0, 6));
    $day_names = OfficeHoursDateHelper::weekDays(FALSE);
    $cardinality = $this->getFieldSetting('cardinality_per_day');
    $id = -1;
    foreach ($days as $index => $day) {
      // @todo The theme_function clears values above cardinality. Move it here.
      for ($day_delta = 0; $day_delta < $cardinality; $day_delta++) {
        $id++;
        $elements[$id]['#day'] = $day;
        $elements[$id]['#daydelta'] = $day_delta;
        $elements[$id]['#dayname'] = $day_names[$day];

        $elements[$id]['#type'] = 'office_hours_slot';
        $elements[$id]['#default_value'] = isset($indexed_items[$day][$day_delta]) ? $indexed_items[$day][$day_delta]->getValue() : NULL;
        $elements[$id]['#field_settings'] = $element['#field_settings'];
        $elements[$id]['#date_element_type'] = $this->getSetting('date_element_type');
      }
    }

    // Build multi element widget. Copy the description, etc. into the table.
    $header = [
      'title' => $this->t($element['#title']),
      'from' => $this->t('From', [], ['context' => 'A point in time']),
      'to' => $this->t('To', [], ['context' => 'A point in time']),
    ];
    if ($element['#field_settings']['comment']) {
      $header['comment'] = $this->t('Comment');
    }
    $header['operations'] = $this->t('Operations');
    $element['value'] = [
      '#type' => 'office_hours_table',
      '#header' => $header,
      '#tableselect' => FALSE,
      '#tabledrag' => FALSE,
    ] + $element['value'] + $elements;

    // Wrap the table in a collapsible fieldset, which is th only way(?)
    // to show the 'required' asterisk and the help text.
    // The help text is now shown above the table, as requested by some users.
    // N.B. For some reason, the title is shown in Capitals.
    $element['#type'] = 'details';
    // Controls the HTML5 'open' attribute. Defaults to FALSE.
    $element['#open'] = TRUE;
    // Remove field_name from first column.
    $element['value']['#header']['title'] = NULL;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // See also function formElement(), formMultipleElements().
    // Reformat the $values, before passing to database.
    $multiple_values = $this->getPluginDefinition()['multiple_values'];
    if ($multiple_values == 'FALSE') {
      // Below line works fine with Annotation: multiple_values = "FALSE".
      $values = $values['value'];
    }
    elseif ($multiple_values == 'TRUE') {
      // Below lines should work fine with Annotation: multiple_values = "TRUE".
      $values = reset($values)['value'];
    }

    $values = parent::massageFormValues($values, $form, $form_state);

    return $values;
  }

}
