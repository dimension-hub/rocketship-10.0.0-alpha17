<?php

namespace Drupal\disable_language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Class DisableLanguageSettings.
 */
class DisableLanguageSettings extends ConfigFormBase {

  /**
   * RouteProvider.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected $routeProvider;

  /**
   * ConditionManager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\disable_language\Form\DisableLanguageSettings $class */
    $class = parent::create($container);
    $class->setExtraServices($container);
    return $class;
  }

  /**
   * Sets extra services during class creation.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  public function setExtraServices(ContainerInterface $container) {
    $this->routeProvider = $container->get('router.route_provider');
    $this->conditionManager = $container->get('plugin.manager.condition');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'disable_language.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'disable_language_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('disable_language.settings');
    $default = $config->get('redirect_override_routes');
    if (is_array($default)) {
      $default = implode("\n", $default);
    }

    $form['help'] = [
      '#type' => 'item',
      '#title' => $this->t('Help'),
      '#markup' => $this->t("As we can't define appropriate cache invalidation, you will have to clear your cache after you save this form."),
    ];

    $form['redirect_override_routes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Override routes'),
      '#description' => $this->t('Enter route names (one per line) that should redirect to themselves in the correct language instead of the frontpage'),
      '#default_value' => $default,
    ];

    /** @var \Drupal\system\Plugin\Condition\RequestPath $condition */
    $condition = $this->conditionManager->createInstance('request_path');
    $form_state->set(['conditions', 'request_path'], $condition);
    $form['exclude_request_path'] = $condition->buildConfigurationForm([], $form_state);

    foreach ($form['exclude_request_path'] as $form_element_name => $form_element_value) {
      if (isset($form['exclude_request_path'][$form_element_name]['#default_value'])) {
        $form['exclude_request_path'][$form_element_name]['#default_value'] = $config->get('exclude_request_path')[$form_element_name] ?? NULL;
      }
    }
    $form['exclude_request_path']['pages']['#title'] = $this->t('Exclude by path');
    $form['#tree'] = TRUE;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValue('redirect_override_routes');
    $values = $this->explodeToArray($values);

    foreach ($values as $value) {
      try {
        $this->routeProvider->getRouteByName($value);
      }
      catch (RouteNotFoundException $e) {
        $form_state->setError($form['redirect_override_routes'], $e->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $values = $form_state->getValue('redirect_override_routes');
    $values = $this->explodeToArray($values);

    $this->config('disable_language.settings')
      ->set('redirect_override_routes', $values)
      ->save();

    $condition = $form_state->get(['conditions', 'request_path']);
    $condition->submitConfigurationForm($form['exclude_request_path'], SubformState::createForSubform($form['exclude_request_path'], $form, $form_state));
    $condition_configuration = $condition->getConfiguration();

    $this->config('disable_language.settings')
      ->set('exclude_request_path', $condition_configuration)
      ->save();
  }

  /**
   * Given a string with newlines, returns an array with values.
   *
   * @param string $string
   *   The string to explode.
   *
   * @return array|array[]|false|string[]
   *   Return value.
   */
  protected function explodeToArray($string) {
    $array = preg_split("[\n|\r]", $string);
    $array = array_filter($array);
    return $array;
  }

}
