(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.LayoutBuilderLock = {
    attach: function () {

      // Explicitly set display block on the form items. In some circumstances
      // the checkboxes are aligned left, so fix it here since this file
      // is loaded anyway.
      $('.layout-builder-lock-section-settings .form-item').css('display', 'block');

      // Default value for the toggle all checkbox.
      let defaultChecked = $('.layout-builder-lock-section-settings .form-checkboxes .form-checkbox:checked').length === $('.layout-builder-lock-section-settings .form-checkboxes .form-checkbox').length;

      // Prepend the toggle all checkbox.
      let checkbox = '<div class="form-type-checkbox form-item">';
      checkbox += '<input type="checkbox" class="layout-builder-lock-toggle-all form-checkbox" id="layout-builder-lock-toggle-all" /> ';
      checkbox += '<label class="option" for="layout-builder-lock-toggle-all">' + Drupal.t('Toggle all') + '</label>';
      checkbox += '</div>';
      $('.layout-builder-lock-section-settings .form-checkboxes').prepend(checkbox);

      let $toggleAll = $('.layout-builder-lock-toggle-all');

      // Set default value.
      $toggleAll.prop('checked', defaultChecked);

      // Listen on change.
      $toggleAll.on('change', function() {
        let checked = false;
        if ($(this).prop('checked')) {
          checked = true;
        }
        $('.layout-builder-lock-section-settings .form-checkboxes .form-checkbox').prop('checked', checked);
      });

    }
  };

})(jQuery, Drupal);
