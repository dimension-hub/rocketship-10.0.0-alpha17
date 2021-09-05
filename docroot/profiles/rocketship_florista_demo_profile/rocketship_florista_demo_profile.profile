<?php

/**
 * @file
 * Profile main file.
 */

use Drupal\Component\Render\FormattableMarkup;
use Drupal\rocketship_florista_demo_profile\Form\RocketshipFloristaSiteConfigureForm;
use Drupal\views\Views;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\webform\Entity\Webform;

/**
 * Implements hook_install_tasks_alter().
 */
function rocketship_florista_demo_profile_install_tasks_alter(&$tasks, $install_state) {
  // Use our own configure form.
  $tasks['install_configure_form']['function'] = RocketshipFloristaSiteConfigureForm::class;

  // Add our own wrapper to install_profile_modules to suppress messages.
  $tasks['install_profile_modules']['function'] = 'rocketship_florista_demo_profile_install_profile_modules';

  $tasks['install_finished']['function'] = 'rocketship_florista_demo_profile_after_install_finished';
}

/**
 * Implements hook_install_tasks().
 */
function rocketship_florista_demo_profile_install_tasks(&$install_state) {
  return [
    'rocketship_florista_demo_profile_assemble_extra_components' => [
      'display_name' => t('Install extra components'),
      'display' => TRUE,
      'type' => 'batch',
    ],
  ];
}

/**
 * Batch job to assemble rocketship_florista_demo_profile extra components.
 *
 * @param array $install_state
 *   The current install state.
 *
 * @return array
 *   The batch job definition.
 */
function rocketship_florista_demo_profile_assemble_extra_components(array &$install_state) {
  $modules = [
    'rocketship_blocks',
    'rocketship_page',
    'rocketship_florista_demo_content',
  ];

  // Also chuck in any modules required by the selected theme.
  /** @var \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler */
  $themeHandler = \Drupal::service('theme_handler');
  $themes = $themeHandler->rebuildThemeData();
  $theme = $themes['rocketship_theme_demo'];
  $requirements = !empty($theme->info['requirements']) ? $theme->info['requirements'] : [];
  $modules = array_merge($modules, $requirements);

  $batch = _rocketship_florista_demo_profile_assemble_extra_components_batch($modules);

  // Enable the theme as last.
  $batch['operations'][] = [
    'rocketship_florista_demo_profile_install_theme',
    ['rocketship_theme_demo'],
  ];
  // Hide warnings and status messages.
  $batch['operations'][] = [
    'rocketship_florista_demo_profile_postpone_messages',
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
function rocketship_florista_demo_profile_install_theme($theme) {
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
function _rocketship_florista_demo_profile_assemble_extra_components_batch(array $modules) {

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
    'rocketship_florista_demo_profile_postpone_messages',
    (array) TRUE,
  ];

  return $batch;
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
function rocketship_florista_demo_profile_install_profile_modules(array &$install_state) {
  $batch = install_profile_modules($install_state);
  // Add hide message as last batch.
  $batch['operations'][] = [
    'rocketship_florista_demo_profile_postpone_messages',
    (array) TRUE,
  ];

  return $batch;
}

/**
 * Batch function to hide warning messages.
 */
function rocketship_florista_demo_profile_postpone_messages() {
  global $_SESSION;

  $messenger = \Drupal::messenger();
  $messages = $messenger->all();
  $messenger->deleteAll();

  if (!isset($_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'])) {
    $_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'] = [];
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
    if (!isset($_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'][$type])) {
      $_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'][$type] = [];
    }
    $_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'][$type] = array_merge($_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'][$type], $list);
  }
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
function rocketship_florista_demo_profile_after_install_finished(array &$install_state) {

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
  } catch (EntityStorageException $e) {
    // No biggie.
  }

  try {
    // Delete frontpage view.
    $view = Views::getView('frontpage');
    $view->destroy();
    $view->storage->delete();
  } catch (\Exception $e) {
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
          t('Congratulations, you have successfully installed the Florista demo site.') .
          '</p>', []),
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

  foreach ($_SESSION['install_state']['rocketship_florista_demo_profile']['saved_messages'] as $type => $messages) {

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

      if (_rocketship_florista_demo_profile_is_drupal_cli()) {
        // Re-add the messages for CLI.
        $messenger->addMessage($message, $type);
      }
    }
  }

  unset($_SESSION['install_state']['rocketship_florista_demo_profile']);

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
function _rocketship_florista_demo_profile_is_drupal_cli() {
  if (defined('STDIN')) {
    return TRUE;
  }

  if (in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg'])) {
    return TRUE;
  }

  return FALSE;
}
