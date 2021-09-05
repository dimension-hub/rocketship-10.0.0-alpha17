<?php

namespace Drupal\metatag_async_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\metatag\Plugin\Field\FieldWidget\MetatagFirehose;

/**
 * Asynchronous widget for the Metatag field.
 *
 * @FieldWidget(
 *   id = "metatag_async_widget_firehose",
 *   label = @Translation("Advanced meta tags form (async)"),
 *   description = @Translation("Asynchronous widget for more performant entity editting."),
 *   field_types = {
 *     "metatag"
 *   }
 * )
 */
class AsyncMetatagFirehose extends MetatagFirehose {

  /**
   * Ajax callback for the "Customize meta tags" button.
   */
  public static function ajaxFormRefresh(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    // This will be placed inside a details element so remove everything that
    // would make add a nested details element.
    $form = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    $children = Element::children($form);
    $form = array_intersect_key($form, array_flip($children));
    return $form;
  }

  /**
   * Submit callback for the "Customize meta tags" button.
   */
  public static function customizeMetaTagsSubmit(array $form, FormStateInterface $form_state) {
    $form_state->set('metatag_async_widget_customize_meta_tags', TRUE);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], [$field_name]);
    $values = NestedArray::getValue($form_state->getValues(), array_merge($path, [0]));
    // We don't want to override saved meta tags settings if the meta tags
    // fields were not present.
    if (!empty($values) && count($values) === 1 && isset($values['metatag_async_widget_customize_meta_tags'])) {
      NestedArray::unsetValue($form_state->getValues(), $path);
      return;
    }

    parent::extractFormValues($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if ($form_state->get('metatag_async_widget_customize_meta_tags')) {
      $element += parent::formElement($items, $delta, $element, $form, $form_state);
      // Open the meta tags group upon selection.
      $element['#open'] = TRUE;

      // Make sure that basic details is opened by default and all the others
      // are closed.
      foreach (Element::children($element) as $key) {
        if (isset($element[$key]['#type']) && $element[$key]['#type'] == 'details') {
          $element[$key]['#open'] = $key == 'basic';
        }
      }
    }
    else {
      $wrapper_id = Html::getUniqueId('metatag-async-widget-wrapper');
      $element['metatag_async_widget_customize_meta_tags'] = [
        '#type' => 'submit',
        '#name' => 'metatag_async_widget_customize_meta_tags',
        '#value' => $this->t('Customize meta tags'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_called_class(), 'customizeMetaTagsSubmit']],
        '#ajax' => [
          'callback' => [__CLASS__, 'ajaxFormRefresh'],
          'wrapper' => $wrapper_id,
        ],
        '#prefix' => "<span id=\"$wrapper_id\">",
        '#suffix' => "</span>",
      ];

      // Put the form element into the form's "advanced" group.
      // Add the outer fieldset.
      $element += [
        '#type' => 'details',
      ];
      // If the "sidebar" option was checked on the field widget, put the
      // form element into the form's "advanced" group. Otherwise, let it
      // default to the main field area.
      $sidebar = $this->getSetting('sidebar');
      if ($sidebar) {
        $element['#group'] = 'advanced';
      }
    }

    return $element;
  }

}
