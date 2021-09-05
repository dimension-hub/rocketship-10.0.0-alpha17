<?php

namespace Drupal\my_color_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * *
 *
 * @FieldFormatter(
 *   id = "my_color_field_div_formatter",
 *   label = @Translation("Div element with background color"),
 *   field_types = {
 *     "my_color_field"
 *   }
 * )
 */
class MyColorFieldDivFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * Настройки по умолчанию для нашего формата вывода.
   */
  public static function defaultSettings(): array {
    return [
      'width' => '80',
      'height' => '80',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * Форма с настройками для нашего формата вывода.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['width'] = [
      '#type' => 'number',
      '#title' => t('Width'),
      '#field_suffix' => 'px.',
      '#default_value' => $this->getSetting('width'),
      '#min' => 1,
    ];

    $elements['height'] = [
      '#type' => 'number',
      '#title' => t('Height'),
      '#field_suffix' => 'px.',
      '#default_value' => $this->getSetting('height'),
      '#min' => 1,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   *
   * Данный метод позволяет вывести кратку информацию о текущих настройках поля
   * на странице управления отображением.
   */
  public function settingsSummary(): array {
    $summary = [];
    $settings = $this->getSettings();

    $summary[] = t('Width @width px.', ['@width' => $settings['width']]);
    $summary[] = t('Height @height px.', ['@height' => $settings['height']]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => new FormattableMarkup(
          '<div style="width: @width; height: @height; background-color: @color;"></div>',
          [
            '@width' => $settings['width'] . 'px',
            '@height' => $settings['height'] . 'px',
            '@color' => $item->value,
          ]
        ),
      ];
    }

    return $element;
  }

}
