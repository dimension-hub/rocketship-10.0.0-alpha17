(function($) {
  "use strict";
  Drupal.behaviors.simple_recaptcha_buttons = {
    attach: function (context, drupalSettings) {
      function recaptchaButtons(formIds) {
        for(let formId in formIds) {
          const $form = $('form[data-recaptcha-id="' + formId + '"]');
          // Count submit buttons inside of form
          // if there's only 1 submit we're good to go.
          let count = $form.find('input[type="submit"]').length;
          if(count === 1) {
            $form.find('[type="submit"]').addClass('simple-recaptcha-submit');
            continue;
          }

          // Lookup for FAPI primary button ( '#button_type' => 'primary' )
          // @see https://www.drupal.org/node/1848288
          const $primary = $form.find('.button--primary');
          if($primary.length > 0) {
            $form.find('.button--primary').addClass('simple-recaptcha-submit');
            continue;
          }

          // Fallback - last available submit element.
          $form.find('[type="submit"]').last().addClass('simple-recaptcha-submit');

        }
      }
      if (typeof drupalSettings.simple_recaptcha != "undefined") {
        recaptchaButtons(drupalSettings.simple_recaptcha.form_ids);
      }

      if (typeof drupalSettings.simple_recaptcha_v3 != "undefined") {
        recaptchaButtons(drupalSettings.simple_recaptcha_v3.forms);
      }

    }
  }
})(jQuery);
