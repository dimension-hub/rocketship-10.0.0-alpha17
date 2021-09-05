(function($) {
  "use strict";
  Drupal.behaviors.simple_recaptcha = {
    attach: function(context, drupalSettings) {
      // Grab form IDs from settings and loop through them.
       for(let formId in drupalSettings.simple_recaptcha.form_ids) {
         const $form = $('form[data-recaptcha-id="'+formId+'"]');

         $form.once("simple-recaptcha").each(function() {
          // Disable submit buttons on form.
          const $submit = $form.find('.simple-recaptcha-submit');
          $submit.attr("data-disabled", "true");
          $submit.attr('data-html-form-id', $form.attr("id"));
          const formHtmlId = $form.attr("id");
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
              const $captcha = $(this).closest("form").find(".recaptcha-wrapper");

              // If it is a first submission of that form, render captcha widget.
              if ( $captcha.length && typeof captchas[formHtmlId] === "undefined" ) {
                captchas[formHtmlId] = grecaptcha.render($captcha.attr("id"), {
                  sitekey: drupalSettings.simple_recaptcha.sitekey
                });
                $captcha.fadeIn();
                $captcha.addClass('recaptcha-visible');
                e.preventDefault();
              }
              else {
                // Check reCaptcha response.
                const response = grecaptcha.getResponse(captchas[formHtmlId]);

                // Verify reCaptcha response.
                if (typeof response !== "undefined" && response.length ) {
                  e.preventDefault();
                  const $currentSubmit = $('[data-html-form-id="'+formHtmlId+'"]');
                  $form.find('input[name="simple_recaptcha_token"]').val(response);
                  $currentSubmit.removeAttr("data-disabled");
                  // Click goes for regular forms, mousedown for AJAX forms.
                  $currentSubmit.click();
                  $currentSubmit.mousedown();
                }
                else {
                  // Mark captcha widget with error-like border.
                  $captcha.children().css({
                    "border": "1px solid #e74c3c",
                    "border-radius": "4px"
                  });
                  $captcha.addClass('recaptcha-error');
                  e.preventDefault();
                }
              }
            }
          });
        });
      }
    }
  };
})(jQuery);
