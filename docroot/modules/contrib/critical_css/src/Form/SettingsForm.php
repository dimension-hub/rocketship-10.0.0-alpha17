<?php

namespace Drupal\critical_css\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for critical css.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'critical_css_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['critical_css.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('critical_css.settings');

    $form['critical_css_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('enabled'),
      '#description' => $this->t("Enable Critical CSS. Drupal cache must be rebuilt when this value changes."),
    ];

    $form['critical_css_enabled_for_logged_in_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled for logged-in users'),
      '#default_value' => $config->get('enabled_for_logged_in_users'),
      '#description' => $this->t("This option will enable Critical CSS for logged-in users. Since the contents of the critical CSS files are generated emulating an anonymous visit, it is not recommended to enable this option. Keep in mind that Crtical CSS is always disabled for admin routes (i.e.- the ones under /admin) to avoid several conflicts with admin themes."),
    ];

    $form['critical_css_preload_non_critical_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preload non-critical CSS'),
      '#default_value' => $config->get('preload_non_critical_css'),
      '#description' => $this->t("Browsers will load non-critical CSS files with the highest priority. Not recommended unless the site is experiencing FOUC (Flash Of Unstyled Content) issues."),
    ];

    $form['critical_css_help'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Prerequisite: generate critical CSS files'),
      '#description' => $this->t(
        'A critical CSS file MUST be generated in advance for any bundle or entity that you want to have this functionality. Using <a href="@critical_url" target="_blank"><em>critical</em> from Addy Osmani</a> or <a href="@criticalCSS_url" target="_blank"><em>criticalCSS</em> from Filament Group</a> is highly recommended. There are also some <a href="@online_generators_url" target="_blank">Critical CSS online generators</a> to get it without effort, or you could generate it manually, possibly using <a href="@chrome_css_coverage_url">Chrome\'s CSS Coverage</a>.',
        [
          "@critical_url" => "https://github.com/addyosmani/critical",
          "@criticalCSS_url" => "https://github.com/filamentgroup/criticalCSS",
          "@online_generators_url" => "https://www.google.com/search?q=critical+css+online+generator",
          "@chrome_css_coverage_url" => "https://developers.google.com/web/tools/chrome-devtools/coverage",
        ]
      ),
    ];

    $form['critical_css_dir_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Critical CSS base directory (relative to @theme_path)', ['@theme_path' => drupal_get_path('theme', $this->config('system.theme')->get('default'))]),
      '#required' => TRUE,
      '#description' => $this->t('It must start with a leading slash. Enter a directory path relative to current theme, where critical CSS files are located (e.g., /css/critical). Inside that directory, "Critical CSS" will try to find, among others, any file named "{entity_id}.css", "{bundle_type}.css",  or "{url}.css" (e.g., 1234.css, article.css, my-page-url.css, etc). If none of the previous filenames can be found, it will search for a file named "default-critical.css". Please refer to the documentation for a complete list of filenames.'),
      '#default_value' => $config->get('dir_path'),
    ];

    $form['critical_css_excluded_ids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude entity ids from Critical CSS processing'),
      '#required' => FALSE,
      '#description' => $this->t('Enter ids of entities (one per line) which should not be processed. These entities will load standard CSS (synchronously).'),
      '#default_value' => $config->get('excluded_ids'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $criticalCssDirPath = $form_state->getValue('critical_css_dir_path');

    if (substr($criticalCssDirPath, 0, 1) != '/') {
      $form_state->setErrorByName(
        'critical_css_dir_path',
        $this->t('Critical CSS base directory must start with a leading slash.'));
    }

    if (strstr($criticalCssDirPath, '..')) {
      $form_state->setErrorByName(
        'critical_css_dir_path',
        $this->t('Critical CSS base directory must not contain "..".'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('critical_css.settings');
    $config
      ->set('enabled', $form_state->getValue('critical_css_enabled'))
      ->set('enabled_for_logged_in_users', $form_state->getValue('critical_css_enabled_for_logged_in_users'))
      ->set('preload_non_critical_css', $form_state->getValue('critical_css_preload_non_critical_css'))
      ->set('dir_path', $form_state->getValue('critical_css_dir_path'))
      ->set('excluded_ids', $form_state->getValue('critical_css_excluded_ids'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
