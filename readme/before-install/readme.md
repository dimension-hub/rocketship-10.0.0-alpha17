# Before installing

- [Before installing](#before-installing)
  - [Composer](#composer)
  - [Drush](#drush)
  - [Adding modules](#adding-modules)
  - [Apply patches to drupal core, modules, profiles or themes.](#apply-patches-to-drupal-core-modules-profiles-or-themes)
  - [Update drupal core](#update-drupal-core)
  - [Locked modules](#locked-modules)

## Composer

This distribution works best with Composer 2. 

Our own internal best practices are as follows:

- Commit everything, including vendor
- Always add --no-dev or --update-no-dev to all commands to avoid dev dependencies being downloaded and committed

## Drush

Drush comes bundled with the internal version of this distribution. There are plans to also include
it with the open source version.

Minor changes have been made to Drupal to make Drupal 9 still compatible with drush 8.

Both drush 8 and drush 10 are available in etc/drush. The deploy scripts have variables
pointing to these drush versions out of the box.

When developing, you can call either version by calling their full path: 
`../etc/drush/drush[version]/vendor/bin/drush`

## Adding modules

To add a new module to your project you need to use the following command:

```
composer require drupal/module-name --update-no-dev
```

e.g:

```
composer require drupal/ctools --update-no-dev
```

## Apply patches to drupal core, modules, profiles or themes.

Add a new "patches" line in the "extra" section of the composer.json file,
e.g: Example patching admin_toolbar module

```
"patches": {
  "drupal/admin_toolbar": {
    "Go to overview instead of taxonomy admin page": "https://www.drupal.org/files/issues/default_link_taxonomy-2518202-1.patch"
  }
}
```

## Update drupal core

Use `composer update --with-dependencies --no-dev` to update to the latest 
drupal core.

The skeleton is configured to update within the same minor release. A manual
action is required to update Drupal core from, eg, 8.5.x to 8.6.x

## Locked modules

Certain modules are currently locked and should not be updated unless the 
following criteria have been met.

- All alpha releases are locked. If you need to update them, do 
so at your own risk and make sure to test thoroughly!
