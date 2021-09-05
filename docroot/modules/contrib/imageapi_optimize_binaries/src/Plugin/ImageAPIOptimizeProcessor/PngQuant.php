<?php

namespace Drupal\imageapi_optimize_binaries\Plugin\ImageAPIOptimizeProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\imageapi_optimize_binaries\ImageAPIOptimizeProcessorBinaryBase;

/**
 * Uses the PngQuant binary to optimize images.
 *
 * @ImageAPIOptimizeProcessor(
 *   id = "pngquant",
 *   label = @Translation("PngQuant"),
 *   description = @Translation("Uses the PngQuant binary to optimize images.")
 * )
 */
class PngQuant extends ImageAPIOptimizeProcessorBinaryBase {

  /**
   * {@inheritdoc}
   */
  protected function executableName() {
    return 'pngquant';
  }

  /**
   * {@inheritdoc}
   */
  public function applyToImage($image_uri) {
    if ($cmd = $this->getFullPathToBinary()) {

      if ($this->getMimeType($image_uri) === 'image/png') {
        $destination_filename = $this->sanitizeFilename($image_uri);
        $options = [
          '--force',
        ];

        if (!empty($this->configuration['quality'])) {
          // Ensure that we send in any missing values.
          $quality = array_filter($this->configuration['quality']) + [
            'min' => 0,
            'max' => 100
          ];
          $options[] = '--quality=' . escapeshellarg($quality['min'] . '-' . $quality['max']);
        }
        if (!empty($this->configuration['speed'])) {
          $options[] = '--speed=' . escapeshellarg($this->configuration['speed']);
        }
        // Instruct pngoptim to read the source file from stdin, which also
        // means that the compressed image will be on stdout.
        $options[] = '-';

        $arguments = [
          $destination_filename,
        ];
        $option_string = implode(' ', $options);
        $argument_string = implode(' ', array_map('escapeshellarg', $arguments));
        return $this->saveCommandStdoutToFile(escapeshellarg($cmd) . ' ' . $option_string . ' < ' . $argument_string, $destination_filename);
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'speed' => 3,
      'quality' => [
        'min' => 90,
        'max' => 99,
      ],
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['speed'] = [
      '#title' => $this->t('Speed'),
      '#type' => 'select',
      '#options' => array_combine(range(1, 10), range(1, 10)),
      '#default_value' => $this->configuration['speed'],
      '#required' => TRUE,
      '#description' => $this->t('1 (brute-force) to 10 (fastest). The pngquant default is 3. Speed 10 has 5% lower quality, but is about 8 times faster than the default.'),
    ];

    $form['quality'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Quality'),
      '#description' => $this->t('Minimum and Maximum are numbers in the range 0 (worst) to 100 (perfect), similar to JPEG.<br/>pngquant will use the least amount of colors required to meet or exceed the max quality.<br/>If conversion results in quality below the min quality the 24-bit original will be output.'),
    ];

    $form['quality']['min'] = [
      '#title' => $this->t('Minimum'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 100,
      '#step' => 1,
      '#default_value' => $this->configuration['quality']['min'],
    ];

    $form['quality']['max'] = [
      '#title' => $this->t('Maximum'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 100,
      '#step' => 1,
      '#default_value' => $this->configuration['quality']['max'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $quality = $form_state->getValue('quality');
    if (isset($quality['min']) && $quality['max'] && $quality['min'] > $quality['max']) {
      $form_state->setErrorByName('quality][min', $this->t('Minimum quality should be less than or equal to the Maximum quality'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['speed'] = $form_state->getValue('speed');
    $this->configuration['quality'] = $form_state->getValue('quality');
  }

}
