(function($) {
  "use strict";
  Drupal.behaviors.simple_recaptcha_v3 = {
    attach: function(context, drupalSettings) {
      // Grab form IDs from settings and loop through them.
       for( let formId in drupalSettings.simple_recaptcha_v3.forms) {
        const $form = $('form[data-recaptcha-id="'+formId+'"]');
          let formSettings = drupalSettings.simple_recaptcha_v3.forms[formId];
          $form.once("simple-recaptcha").each(function() {
          $form.find('input[name="simple_recaptcha_score"]').val(formSettings.score);

          // Disable submit buttons on form.
          const $submit = $form.find('.simple-recaptcha-submit');
          $submit.attr("data-disabled", "true");
          $submit.attr('data-html-form-id', $form.attr("id"));
          const $captcha = $(this).closest("form").find(".recaptcha-wrapper");
          const captchas = [];

          // AJAX forms - add submit handler to form.beforeSend.
          // Update Drupal.Ajax.prototype.beforeSend only once.
          if (typeof Drupal.Ajax !== 'undefined' && typeof Drupal.Ajax.prototype.beforeSubmitSimpleRecaptchaOriginal === 'undefined') {
            Drupal.Ajax.prototype.beforeSubmitSimpleRecaptchaOriginal = Drupal.Ajax.prototype.beforeSubmit;
            Drupal.Ajax.prototype.beforeSubmit = function (form_values, element_settings, options) {
              let currentFormIsRecaptcha = form_values.find(function (form_id){
                return form_id.value === formId;
              });
              if (currentFormIsRecaptcha !== undefined) {
                let $element = $(this.element);
                let isFormActions = $element
                    .closest('.form-actions').length;
                let $token = $form.find('input[name="simple_recaptcha_token"]').val();
                if (isFormActions && ($token === 'undefined' || $token === '')) {
                  this.ajaxing = false;
                  return false;
                }
              }
              return this.beforeSubmitSimpleRecaptchaOriginal();
            }
          }

          $submit.on("click", function(e) {
            if ($(this).attr("data-disabled") === "true") {
              // Get HTML IDs for further processing.
              const formHtmlId = $form.attr("id");

              // Find captcha wrapper.
              const $captcha = $(this).prev(".recaptcha-v3-wrapper");

              if ( typeof captchas[formHtmlId] === "undefined" ) {
                e.preventDefault();
                $captcha.hide();
                grecaptcha.ready(function() {
                  captchas[formHtmlId] = grecaptcha.execute(drupalSettings.simple_recaptcha_v3.sitekey, {action: formSettings.action}).then(function(token){
                    const $currentSubmit = $('[data-html-form-id="'+formHtmlId+'"]');
                    $form.find('input[name="simple_recaptcha_token"]').val(token);
                    $form.find('input[name="simple_recaptcha_message"]').val(formSettings.error_message);
                    $currentSubmit.removeAttr("data-disabled");
                    // Click goes for regular forms, mousedown for AJAX forms.
                    $currentSubmit.click();
                    $currentSubmit.mousedown();
                  });
                });
              }
              e.preventDefault();
            }
          });
        });
      }
    }
  };
})(jQuery);
