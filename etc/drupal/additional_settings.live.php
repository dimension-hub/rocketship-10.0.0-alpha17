<?php

/**
 * @file
 * The additional settings for the live environment.
 */

define('ROCKETSHIP_PROJECT_ENVIRONMENT', 'live');
define('ROCKETSHIP_MEMCACHE_READY_FOR_USE', FALSE);
define('ROCKETSHIP_PURGE_READY_FOR_USE', FALSE);

include_once DRUPAL_ROOT . '/../etc/drupal/general.settings.php';

// Configure config split directory.
$config['config_split.config_split.whitelist']['status'] = TRUE;
$config['config_split.config_split.blacklist']['status'] = TRUE;
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.dev']['status'] = FALSE;
$config['config_split.config_split.staging']['status'] = FALSE;
$config['config_split.config_split.live']['status'] = TRUE;
// Ignore all non-default collections, meaning languages.
$config['config_ignore.settings']['ignored_config_entities'][9999] = 'collections.*';
