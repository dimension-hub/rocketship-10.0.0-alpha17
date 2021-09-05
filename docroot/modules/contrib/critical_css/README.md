# Critical CSS

Embeds a critical CSS file into a page's HTML head, and loads the rest of
non-critical CSS asynchronously.

## INTRODUCTION ##
 * This module looks for a CSS file inside your theme directory.
   That CSS filename should match any of:
    * bundle type (i.e., "article.css")
    * entity id (i.e., "123.css")
    * url (i.e., "my-article.css")
 * If none of the previous filenames can be found, it will search
   for a file named "default-critical.css".
 * A hook is provided ("critical_css_file_paths_suggestion") in case another
 filename is needed.
 * If any of the above paths is found, it's contents are loaded as
   a string inside a _style_ tag placed into the HTML head.
 * Any other CSS file used in the HTML head is loaded asynchronously using this
   [strategy](https://www.filamentgroup.com/lab/load-css-simpler/). No need for
   polyfills .

## REQUIREMENTS ##
Before this module can do anything, you should generate the critical CSS
of the page.

This can be achieved by running a Gulp task to automatically extract the
critical CSS of any page.
Using Addy Osmani's [critical](https://github.com/addyosmani/critical)
package is highly recommended.

Another option is Filament Group's
[criticalCSS](https://github.com/filamentgroup/criticalCSS).

There are also some critical CSS
[online generators](https://www.google.com/search?q=critical+css+online)
to get that CSS without effort.

The extracted critical CSS must be saved in a directory inside the
current theme, at the location specified on Critical CSS's config page.

## INSTALLATION ##
Simply run `composer require drupal/critical_css`.

## CONFIGURATION ##
It must be enabled in /admin/config/development/performance/critical-css.
This allows for easy enabling/disabling without uninstalling it.

## DEBUGGING ##
When twig debug is enabled, Critical CSS will show all the possible
file paths that is trying to find inside a CSS comment.

If you see ‘NONE MATCHED’, check to see if you are logged in and
make sure to log-out. Since the contents of the critical CSS files
are generated emulating an anonymous visit, I recommend not enabling
this module for logged-in users.
