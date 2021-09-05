<?php

namespace Drupal\dropsolid_rocketship_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManager;

/**
 * ConfigureMultilingualForm class.
 *
 * Defines form for selecting dropsolid_rocketship_profile's Multilingual
 * configuration options form.
 */
class ConfigureMultilingualForm extends FormBase {

  /**
   * Configure Multilingual Form constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translator
   *   The string translation service.
   */
  public function __construct(TranslationInterface $translator) {
    $this->stringTranslation = $translator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dropsolid_rocketship_profile_multilingual_configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array &$install_state = NULL) {

    $standard_languages = LanguageManager::getStandardLanguageList();
    $select_options = [];
    $browser_options = [];

    foreach ($standard_languages as $langcode => $language_names) {
      $select_options[$langcode] = $language_names[0];
      $browser_options[$langcode] = $langcode;
    }

    asort($select_options);
    $default_langcode = $this->configFactory()
      ->getEditable('system.site')
      ->get('default_langcode');

    // Save the default language name.
    $default_language_name = $select_options[$default_langcode];

    // Remove the default language from the list of multilingual languages.
    if (isset($select_options[$default_langcode])) {
      unset($select_options[$default_langcode]);
    }

    if (isset($browser_options[$default_langcode])) {
      unset($browser_options[$default_langcode]);
    }

    $form['#title'] = $this->t('Multilingual configuration');

    $markup = '<b>' . $default_language_name . '</b> ' .
      $this->t("is the default language.");

    $form['multilingual_configuration_introduction'] = [
      '#weight' => -1,
      '#prefix' => '<p>',
      '#markup' => $markup,
      '#suffix' => '</p>',
    ];

    $form['enable_multilingual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set up multiple languages for this site'),
      '#description' => $this->t('Select extra languages to enable for your site.'),
      '#default_value' => FALSE,
    ];

    $form['multilingual_languages'] = [
      '#type' => 'select',
      '#title' => $this->t("Please select your site's other language(s)"),
      '#description' => $this->t('You can skip this and add languages later.'),
      '#options' => $select_options,
      '#multiple' => TRUE,
      '#size' => 8,
      '#attributes' => ['style' => 'width:100%;'],
      '#states' => [
        'visible' => [
          ':input[name="enable_multilingual"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          ':input[name="enable_multilingual"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['actions'] = [
      'continue' => [
        '#type' => 'submit',
        '#value' => $this->t('Save and continue'),
        '#button_type' => 'primary',
      ],
      '#type' => 'actions',
      '#weight' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the value of enable multilingual checkbox.
    $enable_multilingual = $form_state->getValue('enable_multilingual', FALSE);

    if ($enable_multilingual == TRUE) {
      $GLOBALS['install_state']['dropsolid_rocketship_profile']['enable_multilingual'] = TRUE;
    }
    else {
      $GLOBALS['install_state']['dropsolid_rocketship_profile']['enable_multilingual'] = FALSE;
    }

    // Get list of selected multilingual languages.
    $multilingual_languages = $form_state->getValue('multilingual_languages', []);

    if (is_array($multilingual_languages) && count($multilingual_languages) > 0) {
      $multilingual_languages = array_filter($multilingual_languages);
    }
    else {
      $multilingual_languages = [];
    }
    $GLOBALS['install_state']['dropsolid_rocketship_profile']['multilingual_languages'] = $multilingual_languages;

  }

}
