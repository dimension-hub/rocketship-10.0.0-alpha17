<?php

namespace Drupal\simple_recaptcha_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a basic form with file upload widget.
 */
class SimpleRecaptchaTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_recaptcha_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['recaptcha_test_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['recaptcha_test_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Example file'),
      '#required' => FALSE,
      '#upload_location' => 'public://',
      '#multiple' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Form submit'),
      '#attributes' => [
        'id' => 'simple-recaptcha-submit-button',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus('Clicked on ' . $form_state->getTriggeringElement()['#id']);
  }

}
