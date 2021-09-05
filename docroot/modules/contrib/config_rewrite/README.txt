CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Testing
 * Maintainers


INTRODUCTION
------------

The Configuration Rewrite module rewrites existing configuration on module installation via using a "rewrite" folder
in the config directory. Stops with an error, if you are going to rewrite config without having the original config
in your database.

This can be handy, if you like to overwrite existing configuration (like admin user email address) without the need to
use install or update hooks. You can just place your configuration YAML files into the config/rewrite directory in your
module folder. Afterwards the existing configuration will be rewritten on module installation.

 * For a full description of the module visit:
   https://www.drupal.org/project/config_rewrite

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/config_rewrite


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.

INSTALLATION
------------

 * Install the Permissions by Term module as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.

TESTING
-------

This module contains Drupal Kernel Base Tests, which are based on PHPUnit. You can find them in the tests folder.

MAINTAINERS
-----------

 * Brant Wynn - https://www.drupal.org/u/brantwynn

Supporting organiztion:

 * Acquia, Inc - https://www.drupal.org/acquia
