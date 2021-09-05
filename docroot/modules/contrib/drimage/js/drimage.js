(function (document, Drupal) {

  'use strict';

  Drupal.drimage = {};

  Drupal.drimage.findDelayParent = function (el) {
    if (el.parentNode === null) {
      return null;
    }
    if (el.parentNode.classList && el.parentNode.classList.contains('js-delay-drimage')) {
      return el.parentNode;
    }
    return Drupal.drimage.findDelayParent(el.parentNode);
  };

  Drupal.drimage.resize = function (size, r, d) {
    if (size[d] === 0) {
      return size;
    }

    // Clone values into new array.
    var new_size = {
      0: size[0],
      1: size[1]
    };
    new_size[d] = r;

    var inverse_d = Math.abs(d - 1);
    if (size[inverse_d] === 0) {
      return new_size;
    }
    new_size[inverse_d] = Math.round(new_size[inverse_d] * (new_size[d] / size[d]));

    return new_size;
  };

  Drupal.drimage.fetchData = function (el) {
    var data = JSON.parse(el.getAttribute('data-drimage'));
    data.upscale = parseInt(data.upscale);
    data.downscale = parseInt(data.downscale);
    data.threshold = parseInt(data.threshold);
    return data;
  };

  Drupal.drimage.size = function (el) {
    if (el.offsetWidth === 0) {
      return { 0: 0, 1: 0 };
    }

    var data = Drupal.drimage.fetchData(el);
    var size = {
      0: el.offsetWidth,
      1: 0
    };

    // Set height for aspect ratio crop.
    if (data.image_handling === 'aspect_ratio') {
      size[1] = size[0] / data.aspect_ratio.width * data.aspect_ratio.height;
    }

    // Fix blurry images when using background cover option.
    if (data.image_handling === 'background' && data.background.size === 'cover') {
      // Example: available space = 200w, 700h, original image = 1600w, 900h
      // It would be scaled to a 200w, 112h and then be stretched to 700h
      // Calculate what height we would get by using the width of the container.
      // If that calculated height is less then the container height,
      // we need to resize our width to at least that height-ratio.
      var img = el.querySelectorAll('img');
      if (img.length > 0) {
        var width = parseInt(img[0].getAttribute('width'));
        var height = parseInt(img[0].getAttribute('height'));
        var calculated_height = height / width * size[0];
        if (calculated_height < el.offsetHeight) {
          size[0] = size[0] / calculated_height * el.offsetHeight;
        }
      }
    }

    // Get the screen multiplier to deliver higher quality images.
    var multiplier = 1;
    if (data.multiplier === 1) {
      multiplier = Number(window.devicePixelRatio);
      if (isNaN(multiplier) === true || multiplier <= 0) {
        multiplier = 1;
      }
    }
    size[0] = Math.round(size[0] * multiplier);
    size[1] = Math.round(size[1] * multiplier);

    // Make sure the requested image isn't to small.
    if (size[0] < data.upscale) {
      size = Drupal.drimage.resize(size, data.upscale, 0);
    }

    // Reduce all widths to a multiplier of the threshold, starting at the
    // minimal upscaling.
    var w = size[0] - data.upscale;

    var r = (Math.ceil(w / data.threshold) * data.threshold) + data.upscale;
    // When the multiplier is > 1 we can use a slightly smaller image style as
    // long as the resulting width is at least the original un-multiplied width.
    if (multiplier > 1) {
      var r_alt = (Math.floor(w / data.threshold) * data.threshold) + data.upscale;
      if (r_alt >= size[0] / multiplier) {
        r = r_alt;
      }
    }
    size = Drupal.drimage.resize(size, r, 0);

    // Downscale the image if it is to larger.
    if (size[0] > data.downscale) {
      size = Drupal.drimage.resize(size, data.downscale, 0);
    }

    return size;
  };

  Drupal.drimage.init = function (context) {
    if (typeof context === 'undefined') {
      context = document;
    }
    var el = context.querySelectorAll('.drimage');
    if (el.length > 0) {
      for (var i = 0; i < el.length; i++) {
        var data = Drupal.drimage.fetchData(el[i]);
        // Setup some properties for images that will have a fixed aspect ratio:
        if (data.image_handling === 'aspect_ratio') {
          var img = el[i].querySelectorAll('img');
          if (img.length > 0) {
            var width = parseInt(img[0].getAttribute('width'));
            var height = width / data.aspect_ratio.width * data.aspect_ratio.height;
            img[0].setAttribute('height', height);
          }
        }

        Drupal.drimage.renderEl(el[i]);

        // Setup some properties for images that will render as backgrounds.
        // set class on wrapper (+css properties that are configurable)
        if (data.image_handling === 'background') {
          if (!el[i].classList.contains('is-background-image')) {
            el[i].style.backgroundAttachment = data.background.attachment;
            el[i].style.backgroundPosition = data.background.position;
            el[i].style.backgroundSize = data.background.size;
            el[i].classList.add('is-background-image');
          }
        }
      }
    }
  };

  Drupal.drimage.renderEl = function (el) {
    var delay = Drupal.drimage.findDelayParent(el);
    if (delay === null) {
      var rect = el.getBoundingClientRect();
      var data = Drupal.drimage.fetchData(el);
      if ((rect.top + data.lazy_offset >= 0 && rect.top - data.lazy_offset <= (window.innerHeight || document.documentElement.clientHeight))
        || (rect.bottom + data.lazy_offset >= 0 && rect.bottom - data.lazy_offset <= (window.innerHeight || document.documentElement.clientHeight))) {
        if (isNaN(data.fid) === false && data.fid % 1 === 0 && Number(data.fid) > 0) {
          var size = Drupal.drimage.size(el);
          var w = Number(el.getAttribute('data-w'));
          var h = Number(el.getAttribute('data-h'));
          if (size[0] !== w || size[1] !== h) {
            if (size[0] > 0) {
              el.classList.add('is-loading');
              el.setAttribute('data-w', size[0]);
              el.setAttribute('data-h', size[1]);
              var downloadingImage = new Image();
              downloadingImage.onload = function () {
                var img = el.querySelectorAll('img');
                if (img.length > 0) {
                  img[0].src = this.src;
                }
                if (data.image_handling === 'background') {
                  el.style.backgroundImage = 'url("' + this.src + '")';
                }
                el.classList.remove('is-loading');
              };
              downloadingImage.src = data.subdir + '/drimage/' + size[0] + '/' + size[1] + '/' + data.fid + '/' + encodeURIComponent(data.filename);;
            }
          }
        }
      }
    }
  };

  Drupal.behaviors.drimage = {
    attach: function (context) {
      Drupal.drimage.init(context);
      var timer;
      addEventListener('resize', function () {
        clearTimeout(timer);
        timer = setTimeout(Drupal.drimage.init, 100);
      });
      addEventListener('scroll', function () {
        Drupal.drimage.init(document);
      });
    }
  };

})(document, Drupal);
