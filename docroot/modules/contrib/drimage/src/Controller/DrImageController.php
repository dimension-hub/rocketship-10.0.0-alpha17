<?php

namespace Drupal\drimage\Controller;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\file\Entity\File;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple extension over the default image download controller.
 *
 * We inherit from it so we have all functions and logic available. We just
 * override the way the image is generated to suit the needs of the dynamically
 * generated image styles.
 *
 * Images are scaled by default but cropping can be activated on the formatter
 * settings form.
 * When cropping is not activated a height of 0 is passed to the Controller.
 */
class DrImageController extends ImageStyleDownloadController {

  /**
   * Given a raw width and height: check if it adheres to the settings.
   *
   * @param int $width
   *   The raw requested width.
   * @param int $height
   *   The raw requested height.
   *
   * @return bool
   *   Indicates valid width/height against the settings.
   */
  public function checkRequestedDimensions($width, $height) {
    if ($width != intval($width) || $height != intval($height)) {
      return FALSE;
    }

    // Check if the width is between the defined min/max settings.
    $drimage_config = $this->config('drimage.settings');
    if ($width > $drimage_config->get('downscale') || $width < $drimage_config->get('upscale')) {
      return FALSE;
    }

    // If the width is not at the maximum, check if it is at an exact threshold
    // multiplier, taking into account the minimum value.
    if ($width != $drimage_config->get('downscale')) {
      if (($width - $drimage_config->get('upscale')) % $drimage_config->get('threshold') != 0) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Try and find an image style that matches the requested dimensions.
   *
   * @param array $requested_dimensions
   *   The calculated requested dimensions.
   *
   * @return mixed
   *   A matching image style or NULL if none was found.
   */
  public function findImageStyle(array $requested_dimensions) {
    $focal_point = $this->moduleHandler()->moduleExists('focal_point');

    // Try and get an exact match:
    $name = 'drimage_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
    if ($focal_point) {
      $name = 'drimage_focal_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
    }
    $image_style = ImageStyle::load($name);

    if (empty($image_style)) {
      // If the image has a height we might be able to use an image style with a
      // very small distortion.
      if (isset($requested_dimensions[1]) && $requested_dimensions[1] > 0) {
        $styles = ImageStyle::loadMultiple();
        $current_ratio_distortion_diff = 360;
        foreach ($styles as $name => $style) {
          $drimage_config = $this->config('drimage.settings');
          // Calculate the dimensions from the style name.
          $translated_name = str_replace('drimage_', '', $name);
          if ($focal_point) {
            $translated_name = str_replace('focal_', '', $translated_name);
          }
          // Skip image styles without drimage_ prefix.
          if ($name == $translated_name) {
            continue;
          }
          $dimensions = explode('_', $translated_name);
          // Skip image styles that will only scale.
          if ($dimensions[1] <= 0) {
            continue;
          }

          if ($dimensions[0] == $requested_dimensions[0]) {
            // Find an image style with the least amount of distortion.
            $ratio_distortion = deg2rad($drimage_config->get('ratio_distortion') / 60);
            $ratio = $dimensions[0] / $dimensions[1];
            $requested_ratio = $requested_dimensions[0] / $requested_dimensions[1];
            $calculated_ratio_distortion_diff = abs(atan($ratio) - atan($requested_ratio));
            if ($calculated_ratio_distortion_diff <= $ratio_distortion
              && $calculated_ratio_distortion_diff < $current_ratio_distortion_diff) {
              $current_ratio_distortion_diff = $calculated_ratio_distortion_diff;
              $image_style = $styles[$name];
            }
          }
        }
      }
    }

    // No usable image style could be found, so we will have to create one.
    if (empty($image_style)) {
      // When the site starts from a cold cache situation and a lot of requests
      // come in, the webserver might fail at this point, so try a few times.
      $counter = 0;
      while (empty($image_style) && $counter < 10) {
        usleep(rand(10000, 50000));
        $image_style = $this->createDrimageStyle($requested_dimensions);
        $counter++;
      }
    }

    return $image_style;
  }

  /**
   * Create an image style from the requested dimensions.
   *
   * @param array $requested_dimensions
   *   The array containing the dimensions.
   *
   * @return mixed
   *   The image style or FALSE in case something went wrong.
   */
  public function createDrimageStyle(array $requested_dimensions) {
    $focal_point = $this->moduleHandler()->moduleExists('focal_point');

    $name = 'drimage_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
    $label = 'DrImage (' . $requested_dimensions[0] . 'x' . $requested_dimensions[1] . ')';

    // If focal point module is activated, name the styles accordingly.
    if ($focal_point) {
      $name = 'drimage_focal_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
      $label = 'DrImage Focal (' . $requested_dimensions[0] . 'x' . $requested_dimensions[1] . ')';
    }

    // When multiple images width the same dimension are requested in 1 page
    // we can sometimes trigger errors here. Image style can already be
    // created by another request that came in a few milliseconds before this
    // request. Catch that error and try and use the image style that was
    // already created.
    try {
      $style = ImageStyle::create(['name' => $name, 'label' => $label]);
      $configuration = [
        'uuid' => NULL,
        'weight' => 0,
        'data' => [
          'upscale' => FALSE,
          'width' => NULL,
          'height' => NULL,
        ],
      ];
      $configuration['data']['width'] = $requested_dimensions[0];
      if ($requested_dimensions[1] > 0) {
        $configuration['data']['height'] = $requested_dimensions[1];
      }

      // Height is NULL by default, images are scaled.
      if ($configuration['data']['width'] == NULL || $configuration['data']['height'] == NULL) {
        $configuration['id'] = 'image_scale';
      }
      else {
        $configuration['id'] = 'image_scale_and_crop';

        // If focal point module is activated, use that image style instead.
        if ($focal_point) {
          $configuration['id'] = 'focal_point_scale_and_crop';
        }
      }

      $effect = \Drupal::service('plugin.manager.image.effect')->createInstance($configuration['id'], $configuration);
      $style->addImageEffect($effect->getConfiguration());
      // Allow other modules to alter image style.
      $this->moduleHandler->alter('drimage_image_style', $style);
      $style->save();
      $styles[$name] = $style;
      $image_style = $styles[$name];
    }
    catch (EntityStorageException $e) {
      // Wait a tiny little bit to make sure another request isn't still adding
      // effects to the image style.
      usleep(rand(10000, 50000));
      $image_style = ImageStyle::load($name);
    }
    catch (Exception $e) {
      return NULL;
    }

    return $image_style;
  }

  /**
   * Deliver an image from the requested parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $width
   *    The requested width in pixels that came from the JS.
   * @param int $height
   *    The requested height in pixels that came from the JS.
   * @param int $fid
   *    The file id to render.
   * @param string $filename
   *    The filename, only here for SEO purposes.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function image(Request $request, $width, $height, $fid, $filename) {
    // Bail out if the image is not valid.
    $file = File::load($fid);
    $image = $this->imageFactory->get($file->getFileUri());
    if (!$image->isValid()) {
      return new Response($this->t('Error generating image, invalid file.'), 500);
    }

    // Bail out if the arguments are not numbers.
    if (!is_numeric($width) || !is_numeric($height) || !is_numeric($fid)) {
      $error_msg = $this->t('Error generating image, invalid parameters.');
    }

    // The Javascript should have generated a nice size adhering to the
    // threshold and x/y up/down-scaling settings. Check if it actually did.
    // Return the fallback image if it didn't.
    if (!$this->checkRequestedDimensions($width, $height)) {
      $error_msg = $this->t('Error generating image, invalid dimensions.');
    }

    // Try and find a matching image style.
    $requested_dimensions = [0 => $width, 1 => $height];
    $image_style = $this->findImageStyle($requested_dimensions);
    if (empty($image_style)) {
      $error_msg = $this->t('Could not find matching image style.');
    }

    // Variable translation to make the original imageStyle deliver method work.
    $image_uri = explode('://', $file->getFileUri());
    $scheme = $image_uri[0];
    $request->query->set('file', $image_uri[1]);

    // Use the fallback image style if something went wrong.
    if (!empty($error_msg)) {
      $drimage_config = $this->config('drimage.settings');
      $fallback_style = $drimage_config->get('fallback_style');
      if (!empty($fallback_style)) {
        $image_style = ImageStyle::load($fallback_style);
      }
    }

    if (!empty($image_style)) {
      // Because drimage does not use itok, we simulate it.
      if (!$this->config('image.settings')->get('allow_insecure_derivatives')) {
        $image_uri = $image_uri[0] . '://' . $image_uri[1];
        $request->query->set(IMAGE_DERIVATIVE_TOKEN, $image_style->getPathToken($image_uri));
      }

      // Uncomment to test the loading effect:
      //usleep(1000000);

      $response = $this->deliver($request, $scheme, $image_style);
      $drimage_config = $this->config('drimage.settings');
      $proxy_cache_maximum_age = $drimage_config->get('proxy_cache_maximum_age');
      if (!empty($proxy_cache_maximum_age)) {
        $response->setMaxAge($proxy_cache_maximum_age);
      }

      return $response;
    }

    return new Response($error_msg, 500);
  }

}
