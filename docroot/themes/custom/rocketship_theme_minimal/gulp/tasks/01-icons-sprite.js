'use strict';

const gulp = require('gulp'),
    notify = require('gulp-notify'),
    imagemin = require('gulp-imagemin'),
    svgo = require('imagemin-svgo');

const { config } = require('../config');
const { errorNotification } = require('./00-setup');

const $ = {
  svgSprite: require('gulp-svg-sprite'),
  size: require('gulp-size'),
};

const iconsSpriteClasses = function() {
  return gulp.src(config.sprite.src)
  .pipe($.svgSprite({
    shape: {
      spacing: {
        padding: [0, 10, 10, 0]
      },
      dimension: {
        precision: 0
      }
    },
    mode: {
      css: {
        dest: './',
        layout: 'diagonal',
        sprite: config.sprite.svg,
        bust: false,
        render: {
          scss: {
            dest: config.sprite.css2,
            template: config.sprite.template2
          }
        }
      }
    },
    variables: {
      mapname: 'icons'
    }
  }))
  .pipe(imagemin([
    imagemin.svgo({
      plugins: [
        { removeUselessDefs: false },
        { cleanupIDs: false },
        { removeXMLNS: false },
        { removeViewBox: false }
      ]
    }),
  ]))
  .on('error', function (err) {
    return errorNotification(this, err);
  })
  .pipe(gulp.dest('./'));
};

const iconsSprite = function() {
  return gulp.src(config.sprite.src)
    .pipe($.svgSprite({
      shape: {
        spacing: {
          padding: [0, 10, 10, 0]
        },
        dimension: {
          precision: 0
        }
      },
      mode: {
        css: {
          dest: './',
          layout: 'diagonal',
          sprite: config.sprite.svg,
          bust: false,
          render: {
            scss: {
              dest: config.sprite.css,
              template: config.sprite.template
            }
          }
        }
      },
      variables: {
        mapname: 'icons'
      }
    }))
    .pipe(imagemin([
      imagemin.svgo({
        plugins: [
          { removeUselessDefs: false },
          { cleanupIDs: false },
          { removeXMLNS: false },
          { removeViewBox: false }
        ]
      }),
    ]))
    .on('error', function (err) {
      return errorNotification(this, err);
    })
    .pipe(gulp.dest('./'));
};

/**
 * This task generates a sprite and puts it in images
 *
 */
gulp.task('icons:sprite', function () {
  const a = iconsSpriteClasses();
  const b = iconsSprite();
  return a && b;
});


/**
 * Exports
 *
 */
exports.iconsSprite = iconsSprite;
