/**
 * @file
 * My behaviors.
 */
(function($, Drupal) {

  Drupal.behaviors.dummyExample = {
    attach: function(context, settings) {
      console.log(context, settings);
    },
  };

})(jQuery, Drupal);
