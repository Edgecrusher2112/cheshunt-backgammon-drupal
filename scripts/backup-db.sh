#!/bin/bash

TIMESTAMP=$(date +"%Y%m%d-%H%M%S")

docker compose exec php drush sql:dump | gzip > backups/db-$TIMESTAMP.sql.gz