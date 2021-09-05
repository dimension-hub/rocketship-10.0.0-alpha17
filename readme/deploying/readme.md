# Deploying

It is **VERY IMPORTANT** that you do not commit any dev dependencies to the git 
repo. These pose a security risk if they wind up on live environments. If you 
are unsure if there are dev dependencies in your repo, simply run 
`composer install --no-dev`. If there are any, they will be removed. In fact, 
run it even if you are sure there aren't any dev dependencies in vendor.


## Knowledge is Power
Take a good look at the /config folder. Especially the various split folders. 
Read the documentation for config_ignore and config_split. Read the articles 
linked in a previous readme. Everything is already set up for an "average" 
Drupal installation so you can ignore it and just go for it. But if you need to 
add functionality to only live or do anything more advanced, you'll have to 
understand how this system works.

## Robots and .htaccess
For dev and staging the robots.txt and .htaccess file found in etc/drupal is 
deployed during builds. It is *imperative* that you do not touch the robots.txt 
file there. It is set to disallow everything so that Google and the like can't 
index our dev and staging sites.

For the live environment, you can create a htaccess_live and robots_live.txt 
file. These will be deployed on the live environment during builds. So when, 
for example, updating Drupal Core and you see the robots.txt has been changed, 
feel free to apply those changes to the robots_live.txt file as well BUT NOT 
robots.txt in etc/drupal.

## Memcache
Like on local, you have to make sure Memcache, the module, is enabled before 
you uncomment the Memcache section in your additional_settings file for that 
environment. If you don't, the build will fail.

Luckily, if you've built once to an environment Memcache should be enabled from 
that point forwards. Memcache is set to be enabled on every split except local.

And don't forget to fill in your key_prefix!

## Varnish (Purge)
Varnish is available on all of our servers, so make sure Dropsolid Purge is 
enabled before uncommenting the block. Also don't forget to fill in your 
sitename. The modules required will be enabled upon first build.

It goes without saying that if you downsync or upsync and the end result is that
an environment with Memcache or Varnish uncommented but with a fresh database
where the module isn't enabled is going to end up broken. So use your noggin' 
before syncing. Live to lower should be fine, as should Staging and Dev to 
lower. But Local upsyncing can and probably will break stuff if you don't have 
Memcache and/or Varnish installed and running locally.

## Update scripts
In bash/updates you can find an update script for each environment. These get 
executed during builds. Make sure you know what you're doing if you want to make
any changes in these files. For example, enabling `drush cre -y` on live 
environments has consequences for performance. Discuss any changes here with 
your teamlead.

## Migrations
Some modules, such as Rocketship Content, contain migrations which automatically run when you install
the module. However, if you enable them locally and allow CMI to enable them on 
other environments the migrate can't run because it won't have the correct 
configuration due to the way CMI handles its import. So, in those cases it is 
best to enable those module in a hook_update.

#### Info: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_update_N/8.2.x

#### Example:
In a custom module or add to dropsolid_core module file:

<pre>function modulename_update_1() {
  $modules = array(
    'ctools',
  );
  \Drupal::service('module_installer')->install($modules, TRUE);
}</pre>

## Translations
Configuration translations are all ignored on staging and live. The 
config_ignore patch and settings in additional_settings ensures that. This way 
the client can translate configuration without the fear of losing all that work. 
So before the client gets access to staging, import all translations you can on 
dev and sync dev to staging. Once that's happened, any further translations by 
devs should happen directly on staging and everyone must be VERY CAREFUL not to 
upsync to staging and cause a big loss of data.

*Translations are Content*. Even when they're not. Treat staging as if you 
would live and everything will be fine.
