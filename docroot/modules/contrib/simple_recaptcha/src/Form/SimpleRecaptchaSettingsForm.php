<?php

namespace Drupal\simple_recaptcha\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides administration form for simple_recaptcha module.
 */
class SimpleRecaptchaSettingsForm extends ConfigFormBase {

  const SETTINGS = 'simple_recaptcha.config';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_recaptcha_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['recaptcha'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];

    $form['recaptcha']['recaptcha_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('reCAPTCHA type'),
      '#options' => [
        'v2' => $this->t('reCAPTCHA v2 (checkbox)'),
        'v3' => $this->t('reCAPTCHA v3 (invisible)'),
      ],
      '#default_value' => $config->get('recaptcha_type'),
    ];

    $form['recaptcha']['v3_score'] = [
      '#type' => 'number',
      '#title' => $this->t('desired reCAPTCHA score'),
      '#max' => 100,
      '#min' => 1,
      '#default_value' => $config->get('v3_score'),
      '#states' => [
        'visible' => [
          ':input[name="recaptcha_type"]' => ['value' => 'v3'],
        ],
      ],
      '#description' => $this->t('reCAPTCHA v3 returns a score (100 is very likely a good interaction, 0 is very likely a bot). Based on the score, you can decide when to block form submissions.'),
    ];

    $form['keys_v2'] = [
      '#type' => 'details',
      '#title' => $this->t('reCAPTCHA v2 checkbox'),
      '#open' => TRUE,
    ];

    $form['keys_v2']['site_key'] = [
      '#title' => $this->t('Site key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('site_key'),
      '#description' => $this->t('reCaptcha site key will be used in the HTML/JS code to render and handle reCaptcha widget.'),
    ];

    $form['keys_v2']['secret_key'] = [
      '#title' => $this->t('Secret key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('secret_key'),
      '#description' => $this->t('Secret key will be used internally to connect with reCaptcha API and verify responses.'),
    ];

    $form['keys_v3'] = [
      '#type' => 'details',
      '#title' => $this->t('reCAPTCHA v3 (invisible)'),
      '#open' => TRUE,
    ];

    $form['keys_v3']['site_key_v3'] = [
      '#title' => $this->t('Site key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('site_key_v3'),
      '#description' => $this->t('reCaptcha site key will be used in the HTML/JS code to render and handle reCaptcha widget.'),
    ];

    $form['keys_v3']['secret_key_v3'] = [
      '#title' => $this->t('Secret key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('secret_key_v3'),
      '#description' => $this->t('Secret key will be used internally to connect with reCaptcha API and verify responses.'),
    ];

    $form['form_ids'] = [
      '#type' => 'textarea',
      '#description' => $this->t('Add comma separated list of form ids, e.g.: user_login_form,user_pass,user_register_form.<br />Support wildcard eg: user_* to apply on all user forms.'),
      '#title' => $this->t('Form IDs'),
      '#default_value' => $config->get('form_ids'),
    ];

    $form['recaptcha_use_globally'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('use reCAPTCHA globally'),
      '#default_value' => $config->get('recaptcha_use_globally'),
      '#description' => $this->t(
        'For countries where google.com domain isn\'t allowed - use recaptcha.net to load api.js file (See <a href=":url" target="_blank">documentation</a>).',
        [':url' => 'https://developers.google.com/recaptcha/docs/faq#can-i-use-recaptcha-globally']
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('form_ids', $form_state->getValue('form_ids'))
      ->set('site_key', $form_state->getValue('site_key'))
      ->set('secret_key', $form_state->getValue('secret_key'))
      ->set('site_key_v3', $form_state->getValue('site_key_v3'))
      ->set('secret_key_v3', $form_state->getValue('secret_key_v3'))
      ->set('recaptcha_type', $form_state->getValue('recaptcha_type'))
      ->set('v3_score', $form_state->getValue('v3_score'))
      ->set('recaptcha_use_globally', $form_state->getValue('recaptcha_use_globally'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
