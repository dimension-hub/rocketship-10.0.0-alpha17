<?php

namespace Drupal\drimage\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'dynamic responsive image' formatter.
 *
 * @FieldFormatter(
 *   id = "drimage",
 *   label = @Translation("Dynamic Responsive Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class DrImageFormatter extends ImageFormatter {

  /**
   * Returns the handling options.
   *
   * @return array
   *   The image handling options key|label.
   */
  public function imageHandlingOptions() {
    return [
      'scale' => $this->t('Scale'),
      'aspect_ratio' => $this->t('Fixed aspect ratio crop'),
      'background' => $this->t('Background image'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_handling' => 'scale',
      'aspect_ratio' => [
        'width' => 1,
        'height' => 1,
      ],
      'background' => [
        'attachment' => 'scroll',
        'position' => 'center center',
        'size' => 'cover',
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Do not use an image style here. Drimage calculates one for us.
    unset($element['image_style']);

    $element['image_handling'] = [
      '#type' => 'radios',
      '#title' => $this->t('Image handling'),
      '#default_value' => $this->getSetting('image_handling'),
      '#options' => $this->imageHandlingOptions(),
      'scale' => [
        '#description' => $this->t('The image will be scaled in width untill it fits. This maintains the original aspect ratio of the image.'),
      ],
      'aspect_ratio' => [
        '#description' => $this->t('The image will be scaled and cropped to an exact aspect ratio you define.'),
      ],
      'background' => [
        '#description' => $this->t("Put the image in as background-image. This is useful for images that need a fixed height; images that need cropping to the theme's CSS."),
      ],
    ];

    $element['aspect_ratio'] = [
      '#title' => $this->t('Aspect ratio'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name$="[image_handling]"]' => [
            'value' => 'aspect_ratio',
          ],
        ],
      ],
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Width'),
        '#default_value' => $this->getSetting('aspect_ratio')['width'],
        '#min' => 1,
        '#max' => 100,
        '#step' => 1,
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Height'),
        '#default_value' => $this->getSetting('aspect_ratio')['height'],
        '#min' => 1,
        '#max' => 100,
        '#step' => 1,
      ],
    ];

    $element['background'] = [
      '#title' => $this->t('Background options'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name$="[image_handling]"]' => [
            'value' => 'background',
          ],
        ],
      ],
      'attachment' => [
        '#type' => 'radios',
        '#title' => $this->t('background-attachment'),
        '#options' => [
          'scroll' => 'scroll',
          'fixed' => 'fixed',
        ],
        '#description' => $this->t('Inline CSS for background-attachment: <a href="@url">W3C</a>', ['@url' => 'http://www.w3schools.com/cssref/pr_background-attachment.asp']),
        'scroll' => [
          '#description' => $this->t('The background scrolls along with the element. (default)'),
        ],
        'fixed' => [
          '#description' => $this->t('The background is fixed with regard to the viewport. (parallax effect)'),
        ],
        '#default_value' => $this->getSetting('background')['attachment'],
      ],
      'position' => [
        '#type' => 'textfield',
        '#title' => $this->t('background-position'),
        '#description' => $this->t('Inline CSS for background-position: <a href="@url">W3C</a>', ['@url' => 'http://www.w3schools.com/cssref/pr_background-position.asp']),
        '#default_value' => $this->getSetting('background')['position'],
      ],
      'size' => [
        '#type' => 'radios',
        '#title' => $this->t('background-size'),
        '#options' => [
          'cover' => 'cover',
          'contain' => 'contain',
        ],
        'cover' => [
          '#description' => $this->t('Scale the background image to be as large as possible so that the background area is completely covered by the background image. Some parts of the background image may not be in view within the background positioning area.'),
        ],
        'contain' => [
          '#description' => $this->t('Scale the image to the largest size such that both its width and its height can fit inside the content area'),
        ],
        '#description' => $this->t('Inline CSS for background-size: <a href="@url">W3C</a>', ['@url' => 'http://www.w3schools.com/CSSref/css3_pr_background-size.asp']),
        '#default_value' => $this->getSetting('background')['size'],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $options = $this->imageHandlingOptions();
    $handler = $this->getSetting('image_handling');
    $args = [
      '@image_handling' => $options[$handler],
    ];

    // Add extra options for some handlers.
    if ($handler == 'aspect_ratio') {
      $args['@image_handling'] .= ' (' . $this->getSetting('aspect_ratio')['width'] . ':' . $this->getSetting('aspect_ratio')['height'] . ')';
    }
    elseif ($handler == 'background') {
      $args['@image_handling'] .= ' (' . $this->getSetting('background')['position'] . '/' . $this->getSetting('background')['size'] . ' no-repeat ' . $this->getSetting('background')['attachment'] . ')';
    }

    $summary[] = $this->t('Image handling: @image_handling', $args);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $files = $this->getEntitiesToView($items, $langcode);

    $config = \Drupal::configFactory()->get('drimage.settings');

    $url = Url::fromUri('internal:/')->toString();
    if (substr($url, -1) === '/') {
      $url = substr($url, 0, -1);
    }

    foreach ($elements as $delta => $element) {
      $elements[$delta]['#item_attributes'] = new Attribute();
      $elements[$delta]['#item_attributes']['class'] = ['drimage'];
      $elements[$delta]['#theme'] = 'drimage_formatter';

      $elements[$delta]['#data'] = [
        'fid' => $elements[$delta]['#item']->entity->id(),
        // Add the original filename for SEO purposes.
        'filename' => pathinfo($elements[$delta]['#item']->entity->getFileUri())['basename'],
        // Add needed data for calculations.
        'threshold' => $config->get('threshold'),
        'upscale' => $config->get('upscale'),
        'downscale' => $config->get('downscale'),
        'multiplier' => $config->get('multiplier'),
        'lazy_offset' => $config->get('lazy_offset'),
        'subdir' => $url,
      ];

      // Add image_handling and specific data for the type of handling.
      $elements[$delta]['#data']['image_handling'] = $this->getSetting('image_handling');
      switch ($elements[$delta]['#data']['image_handling']) {
        case 'background':
          $elements[$delta]['#data']['background'] = [
            'attachment' => $this->getSetting('background')['attachment'],
            'position' => $this->getSetting('background')['position'],
            'size' => $this->getSetting('background')['size'],
          ];
          break;

        case 'aspect_ratio':
          $elements[$delta]['#data']['aspect_ratio'] = [
            'width' => $this->getSetting('aspect_ratio')['width'],
            'height' => $this->getSetting('aspect_ratio')['height'],
          ];
          break;

        case 'scale':
        default:
          // Nothing extra needed here.
          break;
      }

      // Get original image data. (non cropped, non processed) This is useful when
      // implementing lightbox-style plugins that show the original image.
      $elements[$delta]['#width'] = $element['#item']->getValue()['width'];
      $elements[$delta]['#height'] = $element['#item']->getValue()['height'];
      $elements[$delta]['#alt'] = $element['#item']->getValue()['alt'];
      $elements[$delta]['#data']['original_width'] = $element['#item']->getValue()['width'];
      $elements[$delta]['#data']['original_height'] = $element['#item']->getValue()['height'];
      $elements[$delta]['#data']['original_source'] = file_url_transform_relative(file_create_url($files[$delta]->getFileUri()));

      // Unset the fallback image.
      unset($elements[$delta]['#image']);
    }

    return $elements;
  }

}
