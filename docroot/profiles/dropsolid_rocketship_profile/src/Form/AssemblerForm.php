<?php

namespace Drupal\dropsolid_rocketship_profile\Form;

use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines form for selecting extra components for the assembler to install.
 */
class AssemblerForm extends FormBase {

  const MODULE_PACKAGE_NAME = 'Rocketship';

  const THEME_PACKAGE_NAME = 'Dropsolid Theme';

  /**
   * The Drupal application root.
   *
   * @var string
   */
  protected $root;

  /**
   * The info parser service.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Assembler Form constructor.
   *
   * @param string $root
   *   The Drupal application root.
   * @param \Drupal\Core\Extension\InfoParserInterface $info_parser
   *   The info parser service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translator
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The Theme Handler service.
   */
  public function __construct($root, InfoParserInterface $info_parser, TranslationInterface $translator, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $themeHandler) {
    $this->root = $root;
    $this->infoParser = $info_parser;
    $this->stringTranslation = $translator;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $themeHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('app.root'),
      $container->get('info_parser'),
      $container->get('string_translation'),
      $container->get('module_handler'),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dropsolid_rocketship_profile_extra_components';
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   Extra compoments modules.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array &$install_state = NULL) {
    $form['#title'] = $this->t('Extra components');
    $form['extra_components_introduction'] = [
      '#weight' => -999,
      '#prefix' => '<p>',
      '#markup' => $this->t("Install additional ready-to-use components for your site."),
      '#suffix' => '</p>',
    ];

    // Include system.admin.inc so we can use the sort callbacks.
    $this
      ->moduleHandler
      ->loadInclude('system', 'inc', 'system.admin');
    // Sort all modules by their names.
    $modules = \Drupal::service('extension.list.module')->reset()->getList();
    uasort($modules, 'system_sort_modules_by_info_name');

    // Set up features.
    foreach ($modules as $filename => $module) {
      // Grab all modules that should be shown here.
      if (empty($module->info['show_during_install'])) {
        continue;
      }
      // Make a fieldset wrapper, NO TREE!
      $form[$filename . '_wrapper'] = [
        '#type' => 'fieldset',
        '#title' => $module->info['group'],
        '#tree' => FALSE,
        '#weight' => isset($module->info['weight']) ? $module->info['weight'] : 0,
      ];

      // Add a link to the FA if available.
      if (!empty($module->info['fa_link'])) {
        $form[$filename . '_wrapper']['extra_info_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'display:block;margin-top:5px;',
          ],
        ];

        $form[$filename . '_wrapper']['extra_info_wrapper']['extra_info'] = [
          '#title' => $this->t('Functional Analysis'),
          '#type' => 'link',
          '#url' => Url::fromUri($module->info['fa_link'], ['attributes' => ['target' => '_blank']]),
        ];
      }

      // Add the module itself as the first module.
      $form[$filename . '_wrapper']['extra_features'][$filename] = [
        '#type' => 'checkbox',
        '#title' => isset($module->info['name_override']) ? $module->info['name_override'] : $this->t('Core'),
        '#description' => $this->t($module->info['description']),
        '#default_value' => (bool) empty($module->info['prechecked']) ? $module->status : $module->info['prechecked'],
        // If already enabled at this point, don't allow them to uncheck it
        // Means it's a dependency of the profile and they can't not install
        // it anyway.
        '#disabled' => (bool) $module->status,
      ];
      // Add states if present.
      if (!empty($module->info['states'])) {
        $form[$filename . '_wrapper']['extra_features'][$filename]['#states']
          = $module->info['states'];
      }
      // Add any upgrades defined in the info.yml.
      if (!empty($module->info['upgrades'])) {
        foreach ($module->info['upgrades'] as $upgrade) {
          list($file, $name) = explode(':', $upgrade);
          $info = $modules[$file];
          $form[$filename . '_wrapper']['extra_features'][$file] = [
            '#type' => 'checkbox',
            '#title' => $this->t($name),
            '#description' => $this->t($info->info['description']),
            '#default_value' => (bool) empty($info->info['prechecked']) ? $info->status : $info->info['prechecked'],
            '#disabled' => (bool) $info->status,
          ];
          // Add states to these upgrades if defined.
          if (!empty($info->info['states'])) {
            $form[$filename . '_wrapper']['extra_features'][$file]['#states']
              = $info->info['states'];
          }
        }
      }
    }

    $form['theme_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site theme'),
      '#tree' => FALSE,
      '#weight' => 50,
    ];
    $form['theme_wrapper']['theme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select your theme'),
      '#options' => [],
      '#required' => TRUE,
      '#title_display' => 'invisible',
      '#default_value' => 'bartik',
    ];

    $themes = $this->themeHandler->rebuildThemeData();
    $excluded = ['seven', 'classy', 'stark', 'stable'];
    foreach ($themes as $filename => $theme) {
      if (in_array($filename, $excluded)) {
        continue;
      }
      $form['theme_wrapper']['theme']['#options'][$filename] = $theme->info['name'] . '<br/><div class="description">' . $theme->info['description'] . '</div>';
      if ($filename == 'rocketship_theme_starter') {
        $form['theme_wrapper']['theme']['#default_value'] = 'rocketship_theme_starter';
      }
    }

    $form['actions'] = [
      'continue' => [
        '#type' => 'submit',
        '#value' => $this->t('Assemble and install'),
        '#button_type' => 'primary',
      ],
      '#type' => 'actions',
      '#weight' => 999,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!isset($GLOBALS['install_state']['dropsolid_rocketship_profile']['extra_features'])) {
      $GLOBALS['install_state']['dropsolid_rocketship_profile']['extra_features'] = [];
    }

    $values = $form_state->cleanValues()->getValues();
    // Unset theme, remaining values are modules to enable.
    unset($values['theme']);

    foreach ($values as $name => $status) {
      if ($status) {
        $GLOBALS['install_state']['dropsolid_rocketship_profile']['extra_features'][] = $name;
      }
    }

    $GLOBALS['install_state']['dropsolid_rocketship_profile']['theme'] = $form_state->getValue('theme', 'dropsolid_starter');
  }

  /**
   * Inserts a key/value pair into an array before given index.
   *
   * @param array $array
   *   The array to work on.
   * @param string $key
   *   The key to insert.
   * @param mixed $value
   *   The value to insert.
   * @param int $index
   *   The index to insert before.
   *
   * @return array
   *   The original array with the new value added before the index.
   */
  protected function insertBeforeIndex(array $array, $key, $value, $index) {
    $array = array_slice($array, 0, $index, TRUE) +
      [$key => $value] +
      array_slice($array, $index, count($array) - $index, TRUE);

    return $array;
  }

}
