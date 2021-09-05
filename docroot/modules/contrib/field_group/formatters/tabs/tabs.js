(function ($) {

  'use strict';

  Drupal.FieldGroup = Drupal.FieldGroup || {};
  Drupal.FieldGroup.Effects = Drupal.FieldGroup.Effects || {};

  /**
   * Implements Drupal.FieldGroup.processHook().
   */
  Drupal.FieldGroup.Effects.processTabs = {
    execute: function (context, settings, group_info) {

      if (group_info.context === 'form') {

        // Add required fields mark to any element containing required fields
        var direction = group_info.settings.direction;
        $(context).find('[data-' + direction + '-tabs-panes]').each(function () {
          var errorFocussed = false;
          $(this).find('> details').once('fieldgroup-effects').each(function () {
            var $this = $(this);
            if (typeof $this.data(direction + 'Tab') !== 'undefined') {

              if ($this.is('.required-fields') && ($this.find('[required]').length > 0 || $this.find('.form-required').length > 0)) {
                $this.data(direction + 'Tab').link.find('strong:first').addClass('form-required');
              }

              if ($('.error', $this).length) {
                $this.data(direction + 'Tab').link.parent().addClass('error');

                // Focus the first tab with error.
                if (!errorFocussed) {
                  Drupal.FieldGroup.setGroupWithfocus($this);
                  $this.data(direction + 'Tab').focus();
                  errorFocussed = true;
                }
              }
            }
          });
        });

      }
    }
  };

  Drupal.behaviors.showTabWithError = {
    attach: function (context, settings) {
      if (typeof $('<input>')[0].checkValidity == 'function') { // Check if browser supports HTML5 validation
        $('.form-submit:not([formnovalidate])').once('showTabWithError').on('click', function() { // Can't use .submit() because HTML validation prevents it from running
          var $this = $(this),
              $form = $this.closest('form'); // Get form of the submit button

          $($form[0].elements).each(function () {
            if (this.checkValidity && !this.checkValidity()) { // First check for details element
              var id = $(this).closest('.field-group-tab').attr('id'); // Get wrapper's id
              $('[href="#' + id + '"]').click(); // Click menu item with id
              return false; // Break loop after first error
            }
          });
        });
      }
    }
  };

})(jQuery, Modernizr);
