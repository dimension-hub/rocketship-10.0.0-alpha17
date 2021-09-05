include .env

#help		:	Print commands help.
.PHONY: help
help:
	@sed -n 's/^##//p' Makefile

## info		:	About the project and site URL.
.PHONY: info
info: url
	@grep -v '^ *#\|^ *$$' .env | head -n15

##
## up		:	Re-create containers or starting up only containers.
.PHONY: up
up:
	@echo "Starting up containers for $(PROJECT_NAME)..."
	docker-compose pull
	docker-compose up -d --remove-orphans

## upnewsite_D8	:	Deployment local new Drupal 8 site.
.PHONY: upnewsite_D8
upnewsite_D8: gitclone8 up coin addsettings druinsi url

.PHONY: drupfan_start_project
drupfan_start_project: dropsolid_rocketship up coin installprofile url

## upnewsite_D9	:	Deployment local new Drupal 9 site.
.PHONY: upnewsite_D9
upnewsite_D9: gitclone9 up coin addsettings druinsi url

## upsite		:	Automatic deploy local site.
#default for Drupal sites: up coin addsettings (restoredb) url.
.PHONY: upsite
upsite: up coin addsettings url

## start		:	Start containers without updating.
.PHONY: start
start:
	@echo "Starting containers for $(PROJECT_NAME) from where you left off..."
	@docker-compose start

## stop		:	Stop containers.
.PHONY: stop
stop:
	@echo "Stopping containers for $(PROJECT_NAME)..."
	@docker-compose stop

##
## shell		:	Access `php` container via shell.
.PHONY: shell
shell:
	docker exec -ti -e COLUMNS=$(shell tput cols) -e LINES=$(shell tput lines) $(shell docker ps --filter name='$(PROJECT_NAME)_php' --format "{{ .ID }}") sh

## composer	:	Executes `composer` command in a specified `COMPOSER_ROOT` directory. Example: make composer "update drupal/core --with-dependencies".
.PHONY: composer
composer:
	docker exec $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") composer --working-dir=$(COMPOSER_ROOT) $(filter-out $@,$(MAKECMDGOALS))

## drush		:	Executes `drush` command in a specified root site directory. Example: make drush "watchdog:show --type=cron".
.PHONY: drush
drush:
	@docker exec -i $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") drush -r $(COMPOSER_ROOT)/$(SITE_ROOT) $(filter-out $@,$(MAKECMDGOALS))

## phpcs		:	Check codebase with phpcs sniffers to make sure it conforms https://www.drupal.org/docs/develop/standards.
.PHONY: phpcs
phpcs:
	docker run --rm -v $(shell pwd)/$(SITE_ROOT)profiles:/work/profiles -v $(shell pwd)/$(SITE_ROOT)modules:/work/modules -v $(shell pwd)/$(SITE_ROOT)themes:/work/themes $(CODETESTER) phpcs --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme --ignore="*/contrib/*,*.features.*,*.pages*.inc" --colors .

## phpcbf		:	Fix codebase according to Drupal standards https://www.drupal.org/docs/develop/standards.
.PHONY: phpcbf
phpcbf:
	docker run --rm -v $(shell pwd)/$(SITE_ROOT)profiles:/work/profiles -v $(shell pwd)/$(SITE_ROOT)modules:/work/modules -v $(shell pwd)/$(SITE_ROOT)themes:/work/themes $(CODETESTER) phpcbf --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme --ignore="*/contrib/*,*.features.*,*.pages*.inc" --colors .

##
## ps		:	List running containers.
.PHONY: ps
ps:
	@docker ps --filter name='$(PROJECT_NAME)*'

## logs		:	View containers logs.
.PHONY: logs
logs:
	@docker-compose logs -f $(filter-out $@,$(MAKECMDGOALS))

## prune		:	Remove containers and their volumes.
.PHONY: prune
prune:
	@echo "Removing containers for $(PROJECT_NAME)..."
	@docker-compose down -v $(filter-out $@,$(MAKECMDGOALS))

#url		:	Site URL.
.PHONY: url
url:
	@echo "\nSite URL is $(PROJECT_BASE_URL):$(PORT)\n"

#hook		:	Add pre-commit hook for test code.
.PHONY: hook
hook:
	@echo "#!/bin/bash\nmake phpcs" > .git/hooks/pre-commit
	@chmod +x .git/hooks/pre-commit

#gitclone8	:	Gitclone Composer template for Drupal 8 project.
.PHONY: gitclone8
gitclone8:
	@git clone -b 8.x https://github.com/wodby/drupal-vanilla.git
	@cp -af drupal-vanilla/composer.json drupal-vanilla/composer.lock drupal-vanilla/composer.json .
	@wget https://raw.githubusercontent.com/drupal-composer/drupal-project/8.x/.gitignore -O drupal-vanilla/.gitignore
	@sed 'N;$$!P;$$!D;$$d' drupal-vanilla/.gitignore > .gitignore
	@echo "# Ignore other files\n*.tar\n*.tar.gz\n*.sql\n*.sql.gz" >> .gitignore
	@rm -rf drupal-vanilla

.PHONY: dropsolid_rocketship
dropsolid_rocketship:
    @composer create-project dropsolid/rocketship:^8.9@alpha PROJECTNAME --no-dev --no-interaction

#gitclone9	:	Gitclone Composer template for Drupal 9 project.
.PHONY: gitclone9
gitclone9:
	@git clone -b 9.x https://github.com/wodby/drupal-vanilla.git
	@cp -af drupal-vanilla/composer.json drupal-vanilla/composer.lock drupal-vanilla/composer.json .
	@wget https://raw.githubusercontent.com/drupal-composer/drupal-project/9.x/.gitignore -O drupal-vanilla/.gitignore
	@sed 'N;$$!P;$$!D;$$d' drupal-vanilla/.gitignore > .gitignore
	@echo "# Ignore other files\n*.tar\n*.tar.gz\n*.sql\n*.sql.gz" >> .gitignore
	@rm -rf drupal-vanilla

#restoredb	:	Mounts last modified sql database file from root dir.
.PHONY: restoredb
restoredb:
	@echo "\nDeploy `ls *.sql -t | head -n1` database"
	@docker exec -i $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") drush -r $(COMPOSER_ROOT)/$(SITE_ROOT) sql-cli < `ls *.sql -t | head -n1`

#addsettings	:	Сreate settings.php.
.PHONY: addsettings
addsettings:
	@echo "\nСreate settings.php"
	@cp -f $(SETTINGS_ROOT)/default.settings.php $(SETTINGS_ROOT)/settings.php
	@echo '$$settings["hash_salt"] = "randomnadich";' >> $(SETTINGS_ROOT)/settings.php
	@echo '$$settings["config_sync_directory"] = "config/sync";' >> $(SETTINGS_ROOT)/settings.php
	@echo '$$databases["default"]["default"] = array (' >> $(SETTINGS_ROOT)/settings.php
	@echo "  'database' => '$(DB_NAME)'," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'username' => '$(DB_USER)'," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'password' => '$(DB_PASSWORD)'," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'prefix' => ''," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'host' => '$(DB_HOST)'," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'port' => '3306'," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'namespace' => 'Drupal\\\\\\\Core\\\\\\\Database\\\\\\\Driver\\\\\\\mysql'," >> $(SETTINGS_ROOT)/settings.php
	@echo "  'driver' => '$(DB_DRIVER)'," >> $(SETTINGS_ROOT)/settings.php
	@echo ");" >> $(SETTINGS_ROOT)/settings.php
	@mkdir -p $(SITE_ROOT)config/sync
	@sleep 9

#installprofile	:	Install custom profile.
.PHONY: installprofile
installprofile:
	@echo "\nInstalling custom Bohdan profile"
	@cp -afR profiles ./web/
	@cp -afR test_bohdan_module ./web/modules/
	@rm -R profiles/
	@rm -R test_bohdan_module/
	@cp -f $(SETTINGS_ROOT)/default.settings.php $(SETTINGS_ROOT)/settings.php
	@docker exec $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") drush si dropsolid_rocketship_profile --db-url=mysql://$(DB_USER):$(DB_PASSWORD)@$(DB_HOST)/$(DB_NAME) -y
# 	@docker exec $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") composer require drupal/console:~1.0 --prefer-dist --optimize-autoloader
	@docker exec $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") drupal import_configs

#coin		:	Сomposer install.
.PHONY: coin
coin:
	@echo "\nСomposer install"
	@docker exec $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") composer --working-dir=$(COMPOSER_ROOT) install

#druinsi		:	Drush install site.
.PHONY: druinsi
druinsi:
	@docker exec -i $(shell docker ps --filter name='^/$(PROJECT_NAME)_php' --format "{{ .ID }}") drush -r $(COMPOSER_ROOT)/$(SITE_ROOT) si -y standard --account-name=$(DRUPALADMIN) --account-pass=$(DRUPALPASS)

## upenv		:	Update .env file.
.PHONY: upenv
upenv:
	@mv -f .env .env_backup; wget https://raw.githubusercontent.com/wodby/docker4drupal/master/.env -O temp
	@sed 9i\ '\\nCOMPOSER_ROOT=/var/www/html\nSITE_ROOT=web/\nSETTINGS_ROOT=$$(SITE_ROOT)sites/default\nPORT=8272\n\nDRUPALADMIN=admin\nDRUPALPASS=pass' temp > temp2
	@sed 26i\ "CODETESTER=vaple/phpcodesniffer:`date +%y.%m`\n" temp2 > .env
	@rm -f temp temp2
	@echo "\nCreate .env_backup and update .env file"

%:
	@:
