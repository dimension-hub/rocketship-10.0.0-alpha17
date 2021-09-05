<?php

/**
 * @file
 * Profile main file.
 */

use Drupal\Component\Render\FormattableMarkup;
use Drupal\rocketship_core\Form\DefaultContentDefaultLanguage;
use Drupal\views\Views;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\webform\Entity\Webform;
use Drupal\block\Entity\Block;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\dropsolid_rocketship_profile\Form\AssemblerForm;
use Drupal\dropsolid_rocketship_profile\Form\ConfigureMultilingualForm;
use Drupal\dropsolid_rocketship_profile\Form\RocketshipSiteConfigureForm;

/**
 * Implements hook_install_tasks_alter().
 */
function dropsolid_rocketship_profile_install_tasks_alter(&$tasks, $install_state) {
  // Use our own configure form.
  $tasks['install_configure_form']['function'] = RocketshipSiteConfigureForm::class;

  // Add our own wrapper to install_profile_modules to suppress messages.
  $tasks['install_profile_modules']['function'] = 'dropsolid_rocketship_profile_install_profile_modules';

  $tasks['install_finished']['function'] = 'dropsolid_after_install_finished';
}

/**
 * Implements hook_install_tasks().
 */
function dropsolid_rocketship_profile_install_tasks(&$install_state) {

  // Determine whether the enable multilingual option is selected during the
  // Multilingual configuration task.
  $needs_configure_multilingual = (isset($install_state['dropsolid_rocketship_profile']['enable_multilingual']) && $install_state['dropsolid_rocketship_profile']['enable_multilingual'] == TRUE);

  return [
    'dropsolid_rocketship_profile_multilingual_configuration_form' => [
      'display_name' => t('Multilingual configuration'),
      'display' => TRUE,
      'type' => 'form',
      'function' => ConfigureMultilingualForm::class,
    ],
    'dropsolid_rocketship_profile_configure_multilingual' => [
      'display_name' => t('Configure multilingual'),
      'display' => $needs_configure_multilingual,
      'type' => 'batch',
    ],
    'dropsolid_rocketship_profile_configure_default_content_default_language' => [
      'display_name' => t('Configure Default Content Default Language'),
      'type' => 'form',
      'function' => DefaultContentDefaultLanguage::class,
    ],
    'dropsolid_rocketship_profile_extra_components' => [
      'display_name' => t('Extra components'),
      'display' => TRUE,
      'type' => 'form',
      'function' => AssemblerForm::class,
    ],
    'dropsolid_rocketship_profile_assemble_extra_components' => [
      'display_name' => t('Assemble extra components'),
      'display' => TRUE,
      'type' => 'batch',
    ],
  ];
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function dropsolid_rocketship_profile_form_install_select_language_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
  $form['langcode']['#default_value'] = 'en';
  $form['langcode']['#value'] = 'en';
  $form['langcode']['#disabled'] = TRUE;

  $form['info'] = [
    '#type' => 'item',
    // #markup is XSS admin filtered which ensures unsafe protocols will be
    // removed from the url.
    '#markup' => '<p>Rocketship is designed for a default language of English. Using the included <a target="_blank" href="https://www.drupal.org/project/disable_language">Disable Language</a> module you can restrict access to English later on, if needed.</p>',
  ];
}


/**
 * Install modules.
 *
 * Wrapper around install_profile_modules to add a hide warning and status
 * message operation at the end. We don't need to see any module info
 * messages during site installation.
 *
 * @param array $install_state
 *   The current install state.
 *
 * @return array
 *   Return batch array.
 */
function dropsolid_rocketship_profile_install_profile_modules(array &$install_state) {
  $batch = install_profile_modules($install_state);
  // Add hide message as last batch.
  $batch['operations'][] = [
    'dropsolid_rocketship_profile_postpone_messages',
    (array) TRUE,
  ];

  return $batch;
}

/**
 * Batch job to configure multilingual components.
 *
 * @param array $install_state
 *   The current install state.
 *
 * @return array
 *   The batch job definition.
 */
function dropsolid_rocketship_profile_configure_multilingual(array &$install_state) {
  $batch = [];

  // If the multilingual config checkbox was checked.
  if (!empty($install_state['dropsolid_rocketship_profile']['enable_multilingual'])) {
    // Add all selected languages.
    foreach ($install_state['dropsolid_rocketship_profile']['multilingual_languages'] as $language_code) {
      $batch['operations'][] = [
        'dropsolid_rocketship_profile_enable_language',
        (array) $language_code,
      ];
    }

    // Hide warnings and status messages.
    $batch['operations'][] = [
      'dropsolid_rocketship_profile_postpone_messages',
      (array) TRUE,
    ];

  }

  // Fix entity updates to clear up any mismatched entity.
  $batch['operations'][] = [
    'dropsolid_rocketship_profile_fix_entity_update',
    (array) TRUE,
  ];

  return $batch;
}

/**
 * Batch function to hide warning messages.
 */
function dropsolid_rocketship_profile_postpone_messages() {
  global $_SESSION;

  $messenger = \Drupal::messenger();
  $messages = $messenger->all();
  $messenger->deleteAll();

  if (!isset($_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'])) {
    $_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'] = [];
  }

  // Save all messages to output them at the end.
  foreach ($messages as $type => $list) {
    foreach ($list as $idx => $message) {
      $needles = [
        'This site has only a single language enabled',
        'Enable translation for content types',
      ];
      foreach ($needles as $needle) {
        $message = strip_tags((string) $message);
        if (strpos($message, $needle) === 0) {
          unset($list[$idx]);
        }
      }
    }
    if (!isset($_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'][$type])) {
      $_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'][$type] = [];
    }
    $_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'][$type] = array_merge($_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'][$type], $list);
  }
}

/**
 * Batch function to assemble and install needed extra components.
 *
 * @param string|array $extra_component
 *   Name of the extra component.
 */
function dropsolid_rocketship_profile_assemble_extra_component_then_install($extra_component) {
  \Drupal::service('module_installer')->install((array) $extra_component, TRUE);
}

/**
 * Batch function to add selected languages then fetch all translations.
 *
 * @param string|array $language_code
 *   Language code to install and fetch all traslation.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dropsolid_rocketship_profile_enable_language($language_code) {
  ConfigurableLanguage::createFromLangcode($language_code)->save();
}

/**
 * Batch function to fix entity updates to clear up any mismatched entity.
 *
 * Entity and/or field definitions, The following changes were detected in
 * the entity type and field definitions.
 *
 * @param string|array $entity_update
 *   To entity update or not.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dropsolid_rocketship_profile_fix_entity_update($entity_update) {
  // Removed call to deprecated function. Should no longer be needed either.
}

/**
 * Batch job to assemble dropsolid_rocketship_profile extra components.
 *
 * @param array $install_state
 *   The current install state.
 *
 * @return array
 *   The batch job definition.
 */
function dropsolid_rocketship_profile_assemble_extra_components(array &$install_state) {
  $modules = $install_state['dropsolid_rocketship_profile']['extra_features'] ?: [];

  // Also chuck in any modules required by the selected theme.
  /** @var \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler */
  $themeHandler = \Drupal::service('theme_handler');
  $themes = $themeHandler->rebuildThemeData();
  $theme = $themes[$GLOBALS['install_state']['dropsolid_rocketship_profile']['theme']];
  $requirements = !empty($theme->info['requirements']) ? $theme->info['requirements'] : [];
  $modules = array_merge($modules, $requirements);

  $batch = _dropsolid_rocketship_profile_assemble_extra_components_batch($modules);

  // Enable the theme as last.
  $batch['operations'][] = [
    'dropsolid_rocketship_profile_install_theme',
    [$GLOBALS['install_state']['dropsolid_rocketship_profile']['theme']],
  ];
  // Hide warnings and status messages.
  $batch['operations'][] = [
    'dropsolid_rocketship_profile_postpone_messages',
    (array) TRUE,
  ];

  return $batch;
}

/**
 * Installs the chosen theme.
 *
 * @param string $theme
 *   Theme name.
 *
 */
function dropsolid_rocketship_profile_install_theme($theme) {
  \Drupal::service('theme_installer')->install([$theme]);

  // Also set it as the default theme.
  \Drupal::configFactory()->getEditable('system.theme')
    ->set('default', $theme)
    ->save();
}

/**
 * Create batch array for list of modules to be installed.
 *
 * @param array $modules
 *   List of modules.
 *
 * @return array
 *   Batch array.
 */
function _dropsolid_rocketship_profile_assemble_extra_components_batch(array $modules) {

  $files = \Drupal::service('extension.list.module')->reset()->getList();

  // Always install required modules first. Respect the dependencies between
  // the modules.
  $required = [];
  $non_required = [];

  // Add modules that other modules depend on.
  foreach ($modules as $module) {
    if ($files[$module]->requires) {
      $modules = array_merge($modules, array_keys($files[$module]->requires));
    }
  }
  $modules = array_unique($modules);
  foreach ($modules as $module) {
    if (!empty($files[$module]->info['required'])) {
      $required[$module] = $files[$module]->sort;
    }
    else {
      $non_required[$module] = $files[$module]->sort;
    }
  }
  arsort($required);
  arsort($non_required);

  $operations = [];
  foreach ($required + $non_required as $module => $weight) {
    $operations[] = [
      '_install_module_batch',
      [$module, $files[$module]->info['name']],
    ];
  }
  $batch = [
    'operations' => $operations,
    'title' => t('Installing @drupal', ['@drupal' => drupal_install_profile_distribution_name()]),
    'error_message' => t('The installation has encountered an error.'),
  ];
  // Hide warnings and status messages.
  $batch['operations'][] = [
    'dropsolid_rocketship_profile_postpone_messages',
    (array) TRUE,
  ];

  return $batch;
}

/**
 * Runs after install is finished.
 *
 * @param array $install_state
 *   The current install state.
 *
 * @return array
 *   Renderable array to output.
 */
function dropsolid_after_install_finished(array &$install_state) {

  global $_SESSION;

  install_finished($install_state);

  // Rebuild permissions.
  node_access_rebuild();

  // Delete "Contact" webform.
  try {
    $form = Webform::load('contact');
    if ($form) {
      $form->delete();
    }
  }
  catch (EntityStorageException $e) {
    // No biggie.
  }

  try {
    // Delete frontpage view.
    $view = Views::getView('frontpage');
    $view->destroy();
    $view->storage->delete();
  }
  catch (\Exception $e) {
    // No biggie. Still part of checklist.
  }

  // Delete the congratulations message
  // and node rebuild message.
  $messenger = \Drupal::messenger();
  $messenger->deleteAll();

  $output = [
    '#title' => t('Installation finished'),
    'info' => [
      '#type' => 'container',
      'congratulations' => [
        '#markup' => new FormattableMarkup('<p>' .
          t('Congratulations, you have successfully installed Dropsolid Rocketship Profile') .
          '</p>', []),
      ],
      'drush_info' => [
        '#markup' => new FormattableMarkup('<p><strong style="background-color: #ffa500">' .
          t('If you wish to fully setup your local environment, please run the following drush command from just inside the docroot. It will setup all the required config split folders and populate them.') .
          '</strong></p>', []),
      ],
      'drush' => [
        '#markup' => new FormattableMarkup('<pre><code style="background-color: #444040; color: #ffffff;display:block;padding:20px;">drush d-set</code></pre>', []),
      ],
    ],
    'messages' => [
      '#type' => 'details',
      '#title' => t('Messages'),
      '#description' => t('Messages output during install'),
    ],
    'visit_site' => [
      '#markup' => '<a href="/">' . t('Visit your website') . '</a>',
    ],
  ];

  foreach ($_SESSION['install_state']['dropsolid_rocketship_profile']['saved_messages'] as $type => $messages) {

    $output['messages'][$type] = [
      '#theme' => 'item_list',
      '#title' => t('Type: @type', ['@type' => $type]),
      '#list_type' => 'ul',
      '#items' => [],
      '#attributes' => [
        'class' => ['color-' . $type],
        'style' => 'margin-bottom:15px;',
      ],
    ];

    foreach ($messages as $message) {
      // For some reason <front> turns into /core/install.php during
      // installation so replace that part and reinsert into
      // FormattableMarkup else it'll escape any HTML.
      $message = str_replace('/core/install.php', '', (string) $message);
      $output['messages'][$type]['#items'][] =
        new FormattableMarkup($message, []);

      if (_dropsolid_rocketship_profile_is_drupal_cli()) {
        // Re-add the messages for CLI.
        $messenger->addMessage($message, $type);
      }
    }
  }

  unset($_SESSION['install_state']['dropsolid_rocketship_profile']);

  if (_dropsolid_rocketship_profile_is_drupal_cli()) {
    $messenger->addMessage(t('If you wish to fully setup your local environment, please run the drush command "d-set" which will create and populate all the config split folders'), 'warning');
  }

  // Set Chosen include setting to only be included on admin pages
  // It's set to everywhere in config so it'll work during the install.
  \Drupal::configFactory()
    ->getEditable('chosen.settings')
    ->set('chosen_include', CHOSEN_INCLUDE_ADMIN)
    ->save();

  // Clear all caches. Mostly because deleting frontpage /node
  // view breaks that path until caches are cleared.
  drupal_flush_all_caches();

  return $output;
}

/**
 * Check if Drupal is running in CLI.
 *
 * @see https://www.drupal.org/project/drupal/issues/2904700
 *
 * @return bool
 *   If Drupal is running in CLI.
 */
function _dropsolid_rocketship_profile_is_drupal_cli() {
  if (defined('STDIN')) {
    return TRUE;
  }

  if (in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg'])) {
    return TRUE;
  }

  return FALSE;
}
