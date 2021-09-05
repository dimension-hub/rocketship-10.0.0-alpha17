/**
 * Cooldrops UI JS: Forms
 *
 */

 (function ($, Drupal, window, document) {

  "use strict";

  // set namespace for frontend UI javascript
  if (typeof window.rocketshipUI == 'undefined') { window.rocketshipUI = {}; }

  var self = window.rocketshipUI;

  ///////////////////////////////////////////////////////////////////////
  // Cache variables available across the namespace
  ///////////////////////////////////////////////////////////////////////



  ///////////////////////////////////////////////////////////////////////
  // Behavior for Images: triggers
  ///////////////////////////////////////////////////////////////////////

  Drupal.behaviors.rocketshipUIImages = {
    attach: function (context, settings) {

      self.checkLazyLoad();

    }
  };

  ///////////////////////////////////////////////////////////////////////
  // Behavior for Images: functions
  ///////////////////////////////////////////////////////////////////////

  self.checkLazyLoad = function() {

    // In an ideal world, we could detect if the browser supports lazy loading,
    // and then we can safely assign the src attributes without instantly triggering an eager image load.
    // However, the implementation is not consistent accross browsers
    // so for now (june 2021), we check support and load a fallback if it's not supported
    if ("loading" in HTMLImageElement.prototype) {

      // find images that should be lazy loaded (and some imgs from formatters we take over the preloader for)
      var lazyImages = document.querySelectorAll('img[loading="lazy"], .drimage img');

      lazyImages.forEach(function(image) {

        if (typeof image.dataset.src !== 'undefined' && (typeof image.src === 'undefined' || image.src === null || image.src === '')) {
          // set the src attribute to trigger a load
          image.src = image.dataset.src;
        }

        // end the preloader

        if (image.closest('.media') !== null) {
          image.closest('.media').classList.add('js-lazy-loaded');
        }

        if (image.closest('.lazy-wrapper') !== null) {
          image.closest('.lazy-wrapper').classList.add('js-lazy-loaded');
        }

        if (image.closest('.drimage') !== null) {
          image.closest('.drimage').classList.add('js-lazy-loaded');
        }

      });
    } else if (typeof drupalSettings.theme_settings.lazy_loading_fallback !== 'undefined' &&
      (drupalSettings.theme_settings.lazy_loading_fallback === true || drupalSettings.theme_settings.lazy_loading_fallback === 1)) {
      // Use our own lazyLoading with Intersection Observers and all that jazz
      // Lazy load images that have a 'loading = "lazy"' prop
      // IF YOU WANT THIS FALLBACK TO WORK, YOU NEED TO SET UP data-src ON THE IMG TAG IN TWIG !!!
      self.lazyLoadFallback();
    }

  };

  /**
   * Lazy load images
   * Src: https://css-tricks.com/tips-for-rolling-your-own-lazy-loading
   * modified for our use
   */
  self.lazyLoadFallback = function() {

    function lazyLoad (elements) {
      elements.forEach(function(image) {

        // for normal images, just check the visibility
        if (image.intersectionRatio > 0) {

          if (typeof image.target.dataset.src !== 'undefined') {

            // set the src attribute to trigger a load
            image.target.src = image.target.dataset.src;
          }

          // end the preloader

          if (image.target.closest('.media') !== null) {
            image.target.closest('.media').classList.add('js-lazy-loaded');
          }

          if (image.target.closest('.lazy-wrapper') !== null) {
            image.target.closest('.lazy-wrapper').classList.add('js-lazy-loaded');
          }

          if (image.target.closest('.drimage') !== null) {
            image.target.closest('.drimage').classList.add('js-lazy-loaded');
          }

          // stop observing this element. Our work here is done!
          observer.unobserve(image.target);

        }

      });
    }

    // check for IntersectionObserver support
    var obserserverSupport = false;
    if ('isIntersecting' in window.IntersectionObserverEntry.prototype) {
      obserserverSupport = true;
    }

    if (obserserverSupport) {
      // Set up the intersection observer to detect when to define
      // and load the real image source
      var options = {
        rootMargin: "100px",
        threshold: 1.0
      };
      var observer = new IntersectionObserver(lazyLoad, options);

      // Tell our observer to observe all image fields that need lazy loading

      // find images that should be lazy loaded (and some imgs from formatters we take over the preloader for)
      var lazyImages = document.querySelectorAll('img[loading="lazy"], .drimage img');

      lazyImages.forEach(function(image) {
        observer.observe(image);
      });
    }

    var lazyExceptions = null;

    if (obserserverSupport) {
      // Some images, we'll just skip the preloading
      // because they cause issues with other JS and are fetched on the spot already anyway
      lazyExceptions = document.querySelectorAll('.modal-content .lazy-wrapper, .modal-content .drimage, .slick-cloned .lazy-wrapper, .slick-cloned .drimage');
    } else {
      // if no support for our observers, simply reset ALL relevant images
      lazyExceptions = document.querySelectorAll('img[loading="lazy"], .drimage img');
    }

    if (lazyExceptions !== null) {

      lazyExceptions.forEach(function(image) {
        if (typeof image.dataset.src !== 'undefined' && !image.classList.contains('js-lazy-loaded')) {
          // set the src attribute to trigger a load
          image.src = image.dataset.src;
        }

        // end the preloader

        if (image.closest('.media') !== null) {
          image.closest('.media').classList.add('js-lazy-loaded');
        }

        if (image.closest('.lazy-wrapper') !== null) {
          image.closest('.lazy-wrapper').classList.add('js-lazy-loaded');
        }

        if (image.closest('.drimage') !== null) {
          image.closest('.drimage').classList.add('js-lazy-loaded');
        }
      });
    }

  };

})(jQuery, Drupal, window, document);
