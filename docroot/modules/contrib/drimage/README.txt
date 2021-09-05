CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Troubleshooting
 * FAQ
 * Maintainers


INTRODUCTION
------------

Dynamic Responsive Image (or drimage) is an alternative to the Responsive Image
Style module (Drupal core). It is meant to take the pain away of configuring and
maintaining responsive image styles by simply not needing any configuration.

 * For a full description of the module, visit the project page:
   https://drupal.org/project/drimage

 * To submit bug reports and feature suggestions, or to track changes:
   https://drupal.org/project/issues/drimage


REQUIREMENTS
------------

This module requires the following modules:

 * Image (D8 core)


RECOMMENDED MODULES
-------------------

 * None


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. See:
   https://drupal.org/documentation/install/modules-themes/modules-8
   for further information.

 * You may want to disable Responsive Image Style module, since this module is
   meant to replace that one.


CONFIGURATION
-------------

 * Make sure the image module is configured the way you want it.

 * For every image field you want rendered with the Dynamic Responsive Image
   style. Go to the "Manage display" screen of the entity where the field is and
   select "Dynamic Responsive Image" as the Image formatter. You can optionally
   choose a fallback image style and link the image.

 * If you want to limit the amount of image styles drimage ceates (to save on
   diskspace) you can set a threshold pixel value for 2 image styles to
   minimally differ at /admin/config/media/drimage. Resulting images will the be
   up-/downscaled in the browser.


TROUBLESHOOTING
---------------

 * Visit the issue queue: https://drupal.org/project/issues/drimage


FAQ
---

 * Visit the project page: https://drupal.org/project/drimage



MAINTAINERS
-----------

Current maintainers:
 * Wesley S. (weseze) - https://www.drupal.org/u/weseze
 * Jurgen R. (JurgenR) - https://www.drupal.org/u/jurgenr

This project has been sponsored by:
 * Wesley S. (weseze) - https://www.drupal.org/u/weseze
 * O2 Agency - http://www.o2agency.be/
