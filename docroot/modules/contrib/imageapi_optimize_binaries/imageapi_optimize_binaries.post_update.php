<?php

/**
 * Update data after change 'progressive' property from boolean to integer type.
 */
function imageapi_optimize_binaries_post_update_update_jpegoptim_settings() {
  $config_factory = \Drupal::configFactory();

  foreach ($config_factory->listAll('imageapi_optimize.pipeline.') as $config_name) {
    $config = $config_factory->getEditable($config_name);
    $processors = $config->get('processors');
    foreach ($processors as &$processor_config) {
      if ($processor_config['id'] === 'jpegoptim') {
        if (is_bool($processor_config['data']['progressive'])) {
          $processor_config['data']['progressive'] = (int) $processor_config['data']['progressive'];
          $config->set('processors', $processors);
          $config->save();
        }
        break;
      }
    }
  }
}
