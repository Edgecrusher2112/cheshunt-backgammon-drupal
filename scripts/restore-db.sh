#!/bin/bash

gunzip < "$1" | docker compose exec -T php drush sql:cli