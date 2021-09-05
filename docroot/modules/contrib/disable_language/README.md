Disable language
===========

Disable language lets you disable languages on Drupal 8 sites. 

It does the following things when you disable a language:
* Filters out the disabled languages in the language switcher

* Filters out the generated links in an Simple XML sitemap 
(https://www.drupal.org/project/simple_sitemap)
  
* Redirects user that don't have permissions to view disabled 
languages


Installation
------------

* Normal module installation procedure. See
https://www.drupal.org/documentation/install/modules-themes/modules-8


Configuration
------------

Configure which roles have the permission to 'View disabled 
languages'

Go to the language overview at `/admin/config/regional/language` and 
edit a language.

Beware don't disable all languages or you might be locked out of 
your site, due to the redirects. 

By default this module will redirect users to the frontpage if they do not 
have permission to view the current page in the selected language, but in the
configuration form located at `/admin/config/regional/language/disable_language` 
you can override which routes to instead redirect to themselves in an 
accessible language instead of the frontpage.

Two routes are already set up this way: 

  - user.reset.login
  - entity.user.edit_form
  
Without these two routes, a reset password link would simply redirect users 
to the frontpage.