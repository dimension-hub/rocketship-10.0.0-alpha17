<?php

/**
 * @file
 * Hooks provided by the Simple Google reCAPTCHA module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter reCAPTCHA access check before building final form elements.
 *
 * Modules may implement this hook to add extra checks
 * before reCAPTCHA validation is attached to the form.
 * Changing $result param to FALSE will leave the form without
 * reCAPTCHA protection.
 *
 * @param mixed $form
 *   Form API array containing form which is about to be protected.
 * @param bool $result
 *   Boolean indicating if reCAPTCHA validation can be skipped.
 */
function hook_simple_recaptcha_bypass_alter(&$form, &$result) {
  if (\Drupal::currentUser()->hasPermission('administer content')) {
    $result = TRUE;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
