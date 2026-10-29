#!/bin/bash

set -euo pipefail

PHP="/opt/plesk/php/8.3/bin/php"
COMPOSER="/opt/psa/var/modules/composer/composer.phar"
APP_ROOT="/var/www/vhosts/cheshuntbackgammon.club/staging"
RELEASES="$APP_ROOT/releases"
NEW_RELEASE=$(date +"%Y%m%d%H%M%S")
RELEASE_PATH="$RELEASES/$NEW_RELEASE"

export PATH="/opt/plesk/php/8.3/bin:$PATH"

mkdir -p "$RELEASES"

git clone \
  --depth 1 \
  --branch staging \
  git@github.com:Edgecrusher2112/cheshunt-backgammon-drupal.git \
  "$RELEASE_PATH"

rm -rf "$RELEASE_PATH/scripts"

cd "$RELEASE_PATH"

echo "Deploying commit $(git rev-parse HEAD) to $RELEASE_PATH"

"$PHP" "$COMPOSER" install \
  --no-dev \
  --no-interaction \
  --optimize-autoloader

# Drupal can make this directory read-only.
chmod u+w web/sites/default

rm -rf web/sites/default/files
ln -sfn "$APP_ROOT/shared/files" web/sites/default/files

if [ ! -f "$APP_ROOT/shared/settings.php" ]; then
  echo "ERROR: Shared settings.php not found"
  exit 1
fi

if [ ! -f web/sites/settings.project.php ]; then
  echo "ERROR: Project settings file not found"
  exit 1
fi

# Remove the existing symlink before writing release-specific settings.
rm -f web/sites/default/settings.php

cat > web/sites/default/settings.php <<'PHP'
<?php

// Load environment settings from the shared directory.
require dirname($app_root, 3) . '/shared/settings.php';

// Apply version-controlled project settings.
require $app_root . '/sites/settings.project.php';
PHP

if [ ! -f vendor/drush/drush/drush.php ]; then
  echo "Drush not found"
  exit 1
fi

# Use the selected PHP binary and enough memory for configuration imports.
# vendor/bin/drush is a Bash launcher; invoke the PHP entry point directly.
DRUSH=("$PHP" -d memory_limit=512M "$RELEASE_PATH/vendor/drush/drush/drush.php" --root="$RELEASE_PATH/web" --uri=https://admin.yoga-herts.co.uk)

"${DRUSH[@]}" updb -y
echo "Importing configuration from $RELEASE_PATH/config/sync..."
"${DRUSH[@]}" cim --source="$RELEASE_PATH/config/sync" -y
"${DRUSH[@]}" cr

# vendor/bin/drush config:set system.development twig_debug 0 -y
# vendor/bin/drush config:set system.development twig_cache_disable 0 -y

echo "Verifying Drupal bootstrap..."
"${DRUSH[@]}" status

if [ -d "$APP_ROOT/current" ] && [ ! -L "$APP_ROOT/current" ]; then
  echo "ERROR: current exists as a directory"
  exit 1
fi

ln -sfn "$RELEASE_PATH" "$APP_ROOT/current.new"
mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
echo "Active release: $(readlink -f "$APP_ROOT/current")"

echo "Restarting PHP-FPM for the active release..."
sudo systemctl restart plesk-php83-fpm

echo "Rebuilding live Drupal caches after activating $NEW_RELEASE..."
"${DRUSH[@]}" cr

cd "$RELEASES"

ls -dt */ | tail -n +6 | while read -r release; do
  # Never remove the active release.
  if [ "${release%/}" = "$NEW_RELEASE" ]; then
    continue
  fi

  echo "Removing old release: $release"

  # Change directory permissions without following shared symlinks.
  sites_default="${release%/}/web/sites/default"
  if [ -d "$sites_default" ] && [ ! -L "$sites_default" ]; then
    if ! chmod u+w "$sites_default"; then
      echo "WARNING: Cannot make $sites_default writable"
      continue
    fi
  fi

  if ! rm -rf "$release"; then
    echo "WARNING: Failed to remove $release"
  fi
done
