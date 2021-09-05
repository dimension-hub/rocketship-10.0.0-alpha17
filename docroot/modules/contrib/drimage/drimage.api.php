<?php

/**
 * @file
 * Hooks and documentation related to drimage module.
 */

use Drupal\image\Entity\ImageStyle;

/**
 * Alter the possible proxy cache periods.
 *
 * @param array $periods
 *   The array of proxy cache periods.
 */
function hook_drimage_proxy_cache_periods_alter(array &$periods) {
  // Set a new proxy cache period
  $periods[] = 32400;
}

/**
 * Alter auto-generated image style.
 *
 * @param ImageStyle $style
 *   Image style to be created.
 */
function hook_drimage_image_style_alter(ImageStyle &$style) {
  // This example adds the `Manual crop` image style effect using a crop type
  // with machine name `custom`. This example does not include checking that
  // the Crop API module, crop type, or other dependencies exist.
  $configuration = [
    'id' => 'crop_crop',
    'data' => [
      'crop_type' => 'custom',
    ],
    'uuid' => NULL,
    'weight' => -50,
  ];
  $effect = \Drupal::service('plugin.manager.image.effect')->createInstance($configuration['id'], $configuration);
  $style->addImageEffect($effect->getConfiguration());
}
