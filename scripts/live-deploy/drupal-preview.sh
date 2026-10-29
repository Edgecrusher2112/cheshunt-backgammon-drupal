#!/bin/bash

set -euo pipefail

PHP="/opt/plesk/php/8.3/bin/php"
COMPOSER="/opt/psa/var/modules/composer/composer.phar"

APP_ROOT="/var/www/vhosts/cheshuntbackgammon.club/staging"
RELEASES="$APP_ROOT/drupal-releases"
CURRENT="$APP_ROOT/drupal-current"

SHARED="$APP_ROOT/shared"
NEW_RELEASE=$(date +"%Y%m%d%H%M%S")

RELEASE_PATH="$RELEASES/$NEW_RELEASE"

export PATH="/opt/plesk/php/8.3/bin:$PATH"

mkdir -p "$RELEASES"

if [ -e "$CURRENT" ] && [ ! -L "$CURRENT" ]; then
  echo "ERROR: Drupal current path exists and is not a symlink"
  exit 1
fi

# Ensure no temporary deployment symlink already exists.
if [ -e "$CURRENT.new" ] || [ -L "$CURRENT.new" ]; then
  echo "ERROR: Temporary Drupal symlink already exists"
  exit 1
fi

export GIT_SSH_COMMAND="ssh -i /var/www/vhosts/cheshuntbackgammon.club/.ssh/id_ed25519_drupal -o IdentitiesOnly=yes -o BatchMode=yes"

git clone \
  --depth 1 \
  --branch staging \
  git@github.com:Edgecrusher2112/cheshunt-backgammon-drupal.git \
  "$RELEASE_PATH"

cd "$RELEASE_PATH"

echo "Deploying commit $(git rev-parse HEAD) to $RELEASE_PATH"

"$PHP" "$COMPOSER" install \
  --no-dev \
  --no-interaction \
  --optimize-autoloader

# Drupal can make this directory read-only.
chmod u+w web/sites/default

# Verify shared resources.
if [ ! -f "$SHARED/settings.php" ]; then
  echo "ERROR: Shared settings.php not found"
  exit 1
fi

if [ ! -d "$SHARED/files" ]; then
  echo "ERROR: Shared files directory not found"
  exit 1
fi

# Link persistent uploaded files.
if [ -e web/sites/default/files ] || [ -L web/sites/default/files ]; then
  echo "ERROR: Public files path already exists"
  exit 1
fi

ln -s "$SHARED/files" web/sites/default/files

# Link environment-specific Drupal settings.
if [ -e web/sites/default/settings.php ] || [ -L web/sites/default/settings.php ]; then
  echo "ERROR: settings.php already exists"
  exit 1
fi

ln -s "$SHARED/settings.php" web/sites/default/settings.php

if [ ! -f vendor/drush/drush/drush.php ]; then
  echo "Drush not found"
  exit 1
fi

# Use the selected PHP binary and enough memory for configuration imports.
# vendor/bin/drush is a Bash launcher; invoke the PHP entry point directly.
DRUSH=("$PHP" -d memory_limit=512M "$RELEASE_PATH/vendor/drush/drush/drush.php" --root="$RELEASE_PATH/web" --uri=https://drupal.cheshuntbackgammon.club/)

"${DRUSH[@]}" updb -y
echo "Importing configuration from $RELEASE_PATH/config/sync..."
"${DRUSH[@]}" cim --source="$RELEASE_PATH/config/sync" -y
"${DRUSH[@]}" cr

# vendor/bin/drush config:set system.development twig_debug 0 -y
# vendor/bin/drush config:set system.development twig_cache_disable 0 -y

echo "Verifying Drupal bootstrap..."
"${DRUSH[@]}" status

# Atomically activate the new Drupal release.
ln -s "$RELEASE_PATH" "$CURRENT.new"
mv -Tf "$CURRENT.new" "$CURRENT"

echo "Active Drupal release: $(readlink -f "$CURRENT")"

echo "Rebuilding live Drupal caches after activating $NEW_RELEASE..."
"${DRUSH[@]}" cr

echo "Drupal preview deployment completed successfully."