<?php

/**
 * @file
 * The additional settings for the dev environment.
 */

define('ROCKETSHIP_PROJECT_ENVIRONMENT', 'dev');
define('ROCKETSHIP_MEMCACHE_READY_FOR_USE', FALSE);
define('ROCKETSHIP_PURGE_READY_FOR_USE', FALSE);

include_once DRUPAL_ROOT . '/../etc/drupal/general.settings.php';

// Configure config split directory.
$config['config_split.config_split.whitelist']['status'] = TRUE;
$config['config_split.config_split.blacklist']['status'] = TRUE;
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.dev']['status'] = TRUE;
$config['config_split.config_split.staging']['status'] = FALSE;
$config['config_split.config_split.live']['status'] = FALSE;
