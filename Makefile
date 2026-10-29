PHP_CONTAINER = cheshunt_backgammon_php

# Sync databases
pull-db-dev:
	@echo "⬇ Pulling DB from VPS → Local..."
	./scripts/db-pull-dev.sh

push-db-dev:
	@echo "⬆ Pushing Local DB → VPS..."
	./scripts/db-push-dev.sh

pull-db-live:
	@echo "⬇ Pushing Live DB → VPS..."
	./scripts/db-pull-live.sh

backup:
	@echo "Creating local backup..."
	./scripts/backup-db.sh

# Docker commands
up:
	docker compose up -d

down:
	docker compose down

stop:
	docker compose stop

prune:
	docker volume prune

# Drush commands
getblocks:
	docker exec $(PHP_CONTAINER) drush sql:query "SELECT b.id AS ID, d.info AS Title, b.uuid AS UUID FROM block_content b INNER JOIN block_content_field_data d ON d.id = b.id WHERE d.default_langcode = 1 ORDER BY d.info;"

cr:
	@echo "Clearing all caches..."
	docker exec -it $(PHP_CONTAINER) drush cr

updb:
	@echo "Updating Drupal database..."
	docker exec -it $(PHP_CONTAINER) drush updb

cex:
	@echo "Exporting Drupal config..."
	docker exec -it $(PHP_CONTAINER) drush cex

cim:
	@echo "Importing Drupal config..."
	docker exec -it $(PHP_CONTAINER) drush cim

logs:
	docker compose logs php --tail=50

# Composer commands
outdated:
	@echo "Checking for outdated modules..."
	composer outdated

update:
	@echo "Updating outdated modules..."
	composer update

# NPM commands
sass:
	@echo "Compiling SASS..."
	npm --prefix web/themes/custom/maintheme run sass


##################################
########## VPS commands ##########
##################################

# Sync Drupal public files to production
sync-files-prod:
	rsync -avz \
		web/sites/default/files/ \
		vfl@77.68.95.111:/var/www/vhosts/cheshuntbackgammon.club/shared/files/

# Sync Drupal public files to staging
sync-files-staging:
	rsync -avz \
		web/sites/default/files/ \
		vfl@77.68.95.111:/var/www/vhosts/cheshuntbackgammon.club/staging/shared/files/

# Clear production cache
cr-prod:
	ssh cheshuntbackgammon@77.68.95.111 \
		"cd /var/www/vhosts/cheshuntbackgammon.club/current && vendor/bin/drush cr"

# Clear staging cache
cr-staging:
	ssh cheshuntbackgammon@77.68.95.111 \
		"cd /var/www/vhosts/cheshuntbackgammon.club/staging/current && vendor/bin/drush cr"

# Open SSH session
ssh:
	ssh root@server.yoga-herts.co.uk

.PHONY: all