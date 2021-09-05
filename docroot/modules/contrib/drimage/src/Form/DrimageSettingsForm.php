<?php

namespace Drupal\drimage\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DrimageSettingsForm.
 *
 * @package Drupal\drimage\Form
 */
class DrimageSettingsForm extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a DrimageSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatterInterface $date_formatter, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory);

    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('module_handler')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'drimage_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'drimage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Provide some feedback on the insecure derivative implementation.
    if (!$this->config('image.settings')->get('allow_insecure_derivatives')) {
      \Drupal::messenger()->addMessage($this->t('The "allow_insecure_derivatives" settings is disabled, but drimage will bypass this.'), 'warning');
    }

    $form['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum difference per image style'),
      '#default_value' => $this->config('drimage.settings')->get('threshold'),
      '#description' => $this->t('A minimum amount 2 image styles have to differ before a new image style is being created. For cropping styles, the biggest dimension is used. This feature will limit your disk space usage, but the quality of images might be less good since the browser has to scale them.'),
      '#min' => 1,
      '#max' => 500,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $form['ratio_distortion'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum allowed ratio distortion'),
      '#default_value' => $this->config('drimage.settings')->get('ratio_distortion'),
      '#description' => $this->t('How much ratio distortion is allowed when trying to reuse image styles that crop images. The aspect ratio of the generated images will be distorted by the browser to keep the exact aspect ratio your CSS rules require. A minimum of 30 minutes is required to allow for small rounding errors.'),
      '#min' => 1,
      '#max' => 3600,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t("minutes. (1° = 60')"),
    ];

    $form['downscale'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum image style width'),
      '#default_value' => $this->config('drimage.settings')->get('downscale'),
      '#description' => $this->t("The maximum width for the biggest image style. Anything bigger will be scaled down to this size unless aspect ratio's and other min/max settings force it otherwise."),
      '#min' => 1,
      '#max' => 10000,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $form['upscale'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum image style width'),
      '#default_value' => $this->config('drimage.settings')->get('upscale'),
      '#description' => $this->t("The minimal width for the smallest image style. Anything smaller will be scaled up to this size unless aspect ratio's and other min/max settings force it otherwise."),
      '#min' => 1,
      '#max' => 500,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $form['multiplier'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable device pixel ratio detection'),
      '#default_value' => $this->config('drimage.settings')->get('multiplier'),
      '#description' => $this->t('Will produce higher quality images on screens that have more physical pixels then logical pixels.'),
    ];

    $form['lazy_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Lazyloader offeset'),
      '#default_value' => $this->config('drimage.settings')->get('lazy_offset'),
      '#description' => $this->t("Images are always lazy loaded once they are in the browser's canvas. This offset value loads them x amount of pixels before they are visible."),
      '#min' => 0,
      '#max' => 5000,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $options = [];
    $styles = ImageStyle::loadMultiple();
    foreach ($styles as $style) {
      $id = $style->id();
      if (!stristr($id, 'drimage_')) {
        $options[$id] = $style->get('label');
      }
    }
    $form['fallback_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback Image Style'),
      '#default_value' => $this->config('drimage.settings')->get('fallback_style'),
      '#description' => $this->t('If drimage cannot find an image style fallback to using this image style instead of generating an error and not showing an image at all.'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $options,
    ];

    $period = [0, 60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400, 2 * 604800, 31536000];
    $this->moduleHandler->alter('drimage_proxy_cache_periods', $period);
    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($period, $period));
    $period[0] = '<' . t('no caching') . '>';
    $form['proxy_cache_maximum_age'] = [
      '#type' => 'select',
      '#title' => t('Reverse proxy cache maximum age'),
      '#default_value' => $this->config('drimage.settings')->get('proxy_cache_maximum_age'),
      '#options' => $period,
      '#description' => t('This is used as the value for max-age in Cache-Control headers for drimages.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
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
    $this->config('drimage.settings')
      ->set('threshold', $form_state->getValue('threshold'))
      ->set('ratio_distortion', $form_state->getValue('ratio_distortion'))
      ->set('upscale', $form_state->getValue('upscale'))
      ->set('downscale', $form_state->getValue('downscale'))
      ->set('multiplier', $form_state->getValue('multiplier'))
      ->set('lazy_offset', $form_state->getValue('lazy_offset'))
      ->set('fallback_style', $form_state->getValue('fallback_style'))
      ->set('proxy_cache_maximum_age', $form_state->getValue('proxy_cache_maximum_age'))
      ->save();
    \Drupal::messenger()->addMessage($this->t('Drimage Settings have been successfully saved.'));
  }

}
