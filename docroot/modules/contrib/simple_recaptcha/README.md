<!---
// phpcs:ignoreFile -- this is not a core file
-->
# Simple Google reCAPTCHA
This module provides Google reCAPTCHA protection for Drupal forms. In comparison to other modules the main goal is to keep configuration as simple as possible, and simple to support following setups/scenarios:

* Caching - support any caching strategies ( Internal page cache, Memcache, Varnish, Purge )
* Multiple instances - support multiple forms rendered on the same page
* Minimum dependencies - avoid dependency of multiple contrib modules, PHP libraries and 3rd party tools

# Supported reCAPTCHA versions
* reCAPTCHA v2 (checkbox)
* reCAPTCHA v3 (invisible)

Chosen protection type can be configured for any form by adding form ID to configuration, and for any webform by adding reCAPTCHA handler

# Installation
Run composer to install the module:
```
$ composer require drupal/simple_recaptcha:^1.0
```

# Configuration
Navigate to /admin/config/services/simple_recaptcha to set up reCAPTCHA keys and desired reCAPTCHA protection type.
Both keys can be found in [Google reCAPTCHA admin dashboard](https://www.google.com/recaptcha/admin/)

## Permissions
This module provides two types of permissions:
* Administer Simple Google reCAPTCHA - allows user to access configuration page
* Bypass Simple Google reCAPTCHA verification - allows to skip reCAPTCHA validation on forms

To configure user permissions visit /admin/people/permissions

## Add reCAPTCHA validation to forms
Currently there are two ways to add reCAPTCHA to forms:
1. By adding form ID of any form at admin/config/services/simple_recaptcha
2. By adding reCAPTCHA handler to the webform - after enabling simple_recaptcha_webform module this can be simply added in webform handlers settings (/admin/structure/webform/manage/{webform}/handlers).

# How does it work
Basically the module is adding custom markup and libraries needed to render and handle reCAPTCHA widget.
Once libraries are attached to the form, JS code will disable form submit buttons.
When user tries to submit the form, following actions be executed, depending on state:
1. Render reCAPTCHA widget - if it is first form submission
2. get reCAPTCHA response - if reCAPTCHA widget is already rendered
3. Add bit of error-like CSS - if reCAPTCHA widget is already rendered, but reCAPTCHA response is missing
4. Send request to /api/simple_recaptcha/verify to validate reCAPTCHA response - if reCAPTCHA response is provided
5. Parse response from /api/simple_recaptcha/verify and unlock form submit buttons if user's response is verified

Internal route /api/simple_recaptcha/verify is implemented because of two reasons:
* to avoid exposition of secret key in page markup
* to avoid problems with CORS policy

## Theming
This modules wraps reCAPTCHA widget with custom `<div>` element, which receives helper CSS classes depending on state:
* .recaptcha, .recaptcha-wrapper - default state, those classes are provided in initial state even when reCAPTCHA widget wasn't rendered yet
* .recaptcha-visible - reCAPTCHA widget was successfully rendered and is ready to use
* .recaptcha-error - reCAPTCHA widget was successfuly rendered, but reCAPTCHA validation response isn't validated yet ( for example: user tries to submit form before dealing with reCAPTCHA challenge)

## Troubleshooting

### CSP
If you're using Content-Security-Policy on your website ( f.e. with [CSP module](https://www.drupal.org/project/csp) ) you might get following errors in browser's console:
```
Refused to load the script
 'https://www.gstatic.com/recaptcha/releases/75nbHAdFrusJCwoMVGTXoHoM/reca...'
because it violates the following Content Security Policy directive: "script-src 'self' 'unsafe-inline' 'unsafe-eval'
```
Check following doc:
https://developers.google.com/recaptcha/docs/faq#im-using-content-security-policy-csp-on-my-website-how-can-i-configure-it-to-work-with-recaptcha

### Form submit buttons
simple_recaptcha adds helper logic to identify primary submit buttons for forms with multiple buttons (aka HTML input[type="submit"] elements) available, for example:
* Forms with file upload widgets
* Forms with Preview buttons
* Multistep webforms

The order of choosing submit button is following:
1. If there's only 1 submit button inside of form - this is pretty straightforward.
2. If there are more buttons, check if form is using `#button_type` properties and choose button with `'#button_type' => 'primary'` property. See [https://www.drupal.org/node/1848288](https://www.drupal.org/node/1848288)
3. Fallback - choose the last available button inside of single form

For custom modules it is highly recommended following Form API `#button_type` logic. This solution is already implemented in core and in most popular contrib modules.

