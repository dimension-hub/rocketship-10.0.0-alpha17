<?php

namespace Drupal\office_hours\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\office_hours\Element\OfficeHoursDatetime;
use Drupal\office_hours\OfficeHoursDateHelper;

/**
 * Plugin implementation of the 'office_hours' field type.
 *
 * @FieldType(
 *   id = "office_hours",
 *   label = @Translation("Office hours"),
 *   description = @Translation("This field stores weekly 'office hours' or 'opening hours' in the database."),
 *   default_widget = "office_hours_default",
 *   default_formatter = "office_hours",
 *   list_class = "\Drupal\office_hours\Plugin\Field\FieldType\OfficeHoursItemList",
 * )
 */
class OfficeHoursItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'day' => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'starthours' => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'endhours' => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'comment' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['day'] = DataDefinition::create('integer')
      ->setLabel(t('Day'))
      ->setDescription("Stores the day of the week's numeric representation (0-6)");
    $properties['starthours'] = DataDefinition::create('integer')
      ->setLabel(t('Start hours'))
      ->setDescription("Stores the start hours value");
    $properties['endhours'] = DataDefinition::create('integer')
      ->setLabel(t('End hours'))
      ->setDescription("Stores the end hours value");
    $properties['comment'] = DataDefinition::create('string')
      ->setLabel(t('Comment'))
      ->addConstraint('Length', ['max' => 255])
      ->setDescription("Stores the comment");

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $defaultStorageSettings = [
      'time_format' => 'G',
      'element_type' => 'office_hours_datelist',
      'increment' => 30,
      'required_start' => FALSE,
      'required_end' => FALSE,
      'limit_start' => '',
      'limit_end' => '',
      'comment' => 1,
      'valhrs' => FALSE,
      'cardinality_per_day' => 2,
    ] + parent::defaultStorageSettings();

    return $defaultStorageSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $settings = $this->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSettings();

    // Get a formatted list of valid hours values.
    $hours = OfficeHoursDateHelper::hours('H', FALSE);
    foreach ($hours as $key => &$hour) {
      if (!empty($hour)) {
        $hrs = OfficeHoursDateHelper::format($hour . '00', 'H:i');
        $ampm = OfficeHoursDateHelper::format($hour . '00', 'g:i a');
        $hour = "$hrs ($ampm)";
      }
    }

    $element['#element_validate'] = [[$this, 'validateOfficeHours']];
    $description = $this->t(
      'The maximum number of time slots, that are allowed per day.
      <br/><strong> Warning! Lowering this setting after data has been created
      could result in the loss of data! </strong><br/> Be careful when using
      more then 2 slots per day, since not all external services (like Google
      Places) support this.');
    $element['cardinality_per_day'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of time slots per day'),
      '#options' => array_combine(range(1, 12), range(1, 12)),
      '#default_value' => $settings['cardinality_per_day'],
      '#description' => $description,
    ];

    // @todo D8 Align with DateTimeDatelistWidget.
    $element['time_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Time notation'),
      '#options' => [
        'G' => $this->t('24 hour time @example', ['@example' => '(9:00)']), // D7: key = 0.
        'H' => $this->t('24 hour time @example', ['@example' => '(09:00)']), // D7: key = 2.
        'g' => $this->t('12 hour time @example', ['@example' => '09:00 am)']), // D7: key = 1.
        'h' => $this->t('12 hour time @example', ['@example' => '(09:00 am)']), // D7: key = 1.
      ],
      '#default_value' => $settings['time_format'],
      '#required' => FALSE,
      '#description' => $this->t('Format of the time in the widget.'),
    ];
    $element['element_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Time element type'),
      '#description' => $this->t('Select the widget type for selecting the time.'),
      '#options' => [
        'office_hours_datelist' => 'Select list',
        'office_hours_datetime' => 'HTML5 time input',
      ],
      '#default_value' => $this->getSetting('element_type'),
    ];
    // @todo D8 Align with DateTimeDatelistWidget.
    $element['increment'] = [
      '#type' => 'select',
      '#title' => $this->t('Time increments'),
      '#default_value' => $settings['increment'],
      '#options' => [
        1 => $this->t('1 minute'),
        5 => $this->t('5 minute'),
        15 => $this->t('15 minute'),
        30 => $this->t('30 minute'),
        60 => $this->t('60 minute'),
      ],
      '#required' => FALSE,
      '#description' => $this->t('Restrict the input to fixed fractions of an hour.'),
    ];

    $element['comment'] = [
      '#type' => 'select',
      '#title' => $this->t('Allow a comment per time slot'),
      '#required' => FALSE,
      '#default_value' => $settings['comment'],
      '#options' => [
        0 => $this->t('No comments allowed'),
        1 => $this->t('Allow comments (HTML tags possible)'),
        2 => $this->t('Allow translatable comments (no HTML)'),
      ]
    ];
    $element['valhrs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate hours'),
      '#required' => FALSE,
      '#default_value' => $settings['valhrs'],
      '#description' => $this->t('Assure that endhours are later then starthours.
        Please note that this will work as long as both hours are set and
        the opening hours are not through midnight.'),
    ];
    $element['required_start'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Start time'),
      '#default_value' => $settings['required_start'],
    ];
    $element['required_end'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require End time'),
      '#default_value' => $settings['required_end'],
    ];
    $element['limit_start'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit hours - from'),
      '#description' => $this->t('Restrict the hours available - select options will start from this hour.'),
      '#default_value' => $settings['limit_start'],
      '#options' => $hours,
    ];
    $element['limit_end'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit hours - until'),
      '#description' => $this->t('Restrict the hours available - select options
         will end at this hour. You may leave \'until\' time empty.
         Use \'00:00\' for closing at midnight.'),
      '#default_value' => $settings['limit_end'],
      '#options' => $hours,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['day'] = mt_rand(0, 6);
    $values['starthours'] = mt_rand(00, 23) * 100;
    $values['endhours'] = mt_rand(00, 23) * 100;
    $values['comment'] = mt_rand(0,1) ? 'additional text': '';
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Note: in Week-widget, day is <> '', in List-widget, day can be ''.
    // Note: test every change with Week/List widget and Select/HTML5 element!
    return OfficeHoursDatetime::isEmpty($this->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = [];
    // @todo When adding parent::getConstraints(), only English is allowed...
    // $constraints = parent::getConstraints();
    $max_length = $this->getSetting('max_length');
    if ($max_length) {
      $constraint_manager = \Drupal::typedDataManager()
        ->getValidationConstraintManager();
      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Length' => [
            'max' => $max_length,
            'maxMessage' => $this->t('%name: may not be longer than @max characters.', [
              '%name' => $this->getFieldDefinition()->getLabel(),
              '@max' => $max_length,
            ]),
          ],
        ],
      ]);
    }
    return $constraints;
  }

  /**
   * Implements the #element_validate callback for storageSettingsForm().
   *
   * Verifies the office hours limits.
   * "Please note that this will work as long as the opening hours are not through midnight."
   * "You may leave 'until' time empty. Use '00:00' for closing at midnight."
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateOfficeHours(array $element, FormStateInterface &$form_state) {
    if (!empty($element['limit_end']['#value']) &&
      $element['limit_end']['#value'] < $element['limit_start']['#value']) {
      $form_state->setError($element['limit_start'], $this->t('%start is later then %end.', [
        '%start' => $element['limit_start']['#title'],
        '%end' => $element['limit_end']['#title'],
      ]));
    }
  }

}
