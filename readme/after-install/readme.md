# After the installation

- [After the installation](#after-the-installation)
	- [Extra setup!](#extra-setup)
			- [Memcache](#memcache)
			- [Private files](#private-files)
	- [Configuration Management](#configuration-management)
			- [Reroute email](#reroute-email)
	- [Log in](#log-in)
	- [Disable the other child themes](#disable-the-other-child-themes)
	- [Project wiki](#project-wiki)
	- [Commit, upsync and build (the first time)](#commit-upsync-and-build-the-first-time)
		- [Commit](#commit)
		- [Upsync](#upsync)
		- [Build](#build)


## Extra setup!
Before doing anything else, make sure your settings.php files requires
your settings_local.php file present in etc/drupal. That file in turn
requires additional_settings.local.php, also present in etc/drupal. It's in that
file you'll be checking some things.

If you're using the open source version, simply directly require additional_settings.local.php
from your settings.php

#### Memcache
IF you have Memcache installed locally (the module), set the variable
`ROCKETSHIP_MEMCACHE_READY_FOR_USE` to `TRUE` in your local additional settings file.
Also go to sites/environments/local.services.yml and see if there is a Memcache
block there. If so, uncomment it.

It is important to only do this AFTER Memcache is enabled, else it will break 
your site until you comment it out again. It'll try to load classes and 
services which only exist if Memcache is enabled.

#### Private files
By default the private files folder is defined as private/files. Make sure it is
writable, and SSH into dev and staging and check that it is writable there too. 
If it is not, create a ticket and assign it to "R&D Project", with High priority.

What folder should be used for private files is defined in the 
additional_settings files. By default we've already set up ../private/files.

## Configuration Management

The skeleton is set up to use configuration management. Be sure to read
this article (all parts) before continuing:
https://dropsolid.com/en/blog/drupal-8-config-management-part-1

After installing your site, if you installed it using the standard Rocketship Profile,
if you haven't done so already, run the
command `drush d-set` which will set up all the splits for you. Make sure the
following folders are writable:

- config/sync
- config/splits/local
- config/splits/dev
- config/splits/staging
- config/splits/live
- config/splits/whitelist

Each environment has its own Config Split: local, dev, staging and live.
`cex` and `cim` will read the current environment and sync the correct config.
 If you want to specifically import or export, say, the live environment's 
 config then there are two ways:

- Either edit your additional_settings.local.php and disable the
local split and enable the live split, then clear caches and run `cim` or 
`cex` as usual.
- Use `csim [env]` or `csex [env]`. Note, however, that this won't
update the normal sync folder or enable/disable modules when importing
configuration. So use with caution, and as everyone should always do,
triplecheck what you will be committing.
- edit the configuration files directly. **Only do this if you're familiar
with the config you're editing.** If you want to change the page cache time
for live, simply edit the system.performance.yml in the live split and set the
max_age to however many seconds you want.

After pulling and/or downsyncing, always run the local update
script in bash/updates. This will execute all needed commands to bring your
local environment up to speed. This script will always use the bundled drush 
version.

When you install a new module, there's no longer any need to add a hook_update
to enable that module. Just make sure you export all configuration and 
`drush cim` will enable the module for you.

#### Reroute email
Before continuing, edit all reroute_email.settings.yml in the splits folders. 
There should be one for dev, local and staging. In those files, set the 
`address` to your team's email address. Eg swift@dropsolid.com. Then run 
`drush cim -y` again to import your change.

That way you can rest assured no emails will be sent to the client accidentally.

## Log in

- `drush uli` will log you in as user 1 (admin).
- `drush uli 2` will let you log in as user 2 (webadmin)
- You can do the same for other environments using the aliases, if you ever 
need it.  
Eg. `drush @myproject.dev uli` will log you in on dev environment

Do NOT set a password yourself for those accounts. A secure password is
generated on site installation, and the correct way to login is (as a dev) using
drush, or as someone else using Jenny.

## Disable the other child themes

For some obscure reason, sometimes both Flex and Starter get installed.

Make sure only 1 of the child themes is installed:
- if you are working on Flex: uninstall Starter
- if you are working on a Solutions project: uninstall Flex

## Project wiki

DO create another user with the webadmin role named after the project. Give that
user a password, such as "changemepls015" and note it down in the project Wiki.
This account will be used by the client.

## Commit, upsync and build (the first time)

### Commit

If this is a fresh install, it is important you create all the branches
needed and do an initial commit of your new site. You will need to have these 
branches:
- **dev** for development
- **staging** for staging (optional, a lot of projects use master)
- **master** for live

### Upsync

After this, you will need to do an upsync to dev and staging, so the site
is accessible on those environments.

`drush sql-sync @myproject.local @myproject.dev`
- upsync from your own environment to development (replace with `.staging`
for staging env)
- using Launchpad, the alias for your local environment is @self

`drush drush core-rsync @myproject.local:%files @myproject.dev:%files`
- upsync the files folder

### Build

And lastly, you will need to build the dev and staging (or master) branches
to dev and staging respectively.
