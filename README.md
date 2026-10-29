# Cheshunt Backgammon

Drupal site for keeping track of backgammon leagues and knockout in East Herts. Uses Docker4Drupal for local development.

* Read the docs on [**how to use**](https://wodby.com/docs/stacks/drupal/local#usage)

## League
TODO

## Knockout

TODO

## Drupal deployment process

**Current setup**

- Local development: Changes are developed on the dev branch.
- Preview branch: The staging branch is intended to trigger automated Drupal preview deployments.
- Preview URL: https://drupal.cheshuntbackgammon.club
- Hosting: Plesk VPS, with Drupal preview isolated from the existing Astro sites.
- Production: Automatic Drupal production deployment is not configured or enabled.

**How preview deployment works**

1. Changes are tested and committed on dev.
2. Approved changes are merged into staging and pushed to GitHub.
3. GitHub Actions starts the Drupal preview deployment workflow.
4. GitHub Actions connects to the VPS using the restricted drupaldeploy SSH account.
5. The VPS runs deploy/drupal-preview.sh as the cheshuntbackgammon system user.
6. The script creates a timestamped Drupal release, installs Composer dependencies, and runs Drupal database updates and configuration imports.
7. The staging/drupal-current symlink switches to the new release.
8. Drupal caches are rebuilt, and the preview site uses the new release.
The deployment script has already been tested manually. The GitHub Actions workflow is not yet enabled or tested end to end.

**Persistent data**

The following survive releases:
- staging/shared/settings.php
- staging/shared/files
- staging/shared/private
- The cbgc_staging database

**Security**

GitHub Actions uses three repository secrets: DRUPAL_PREVIEW_SSH_KEY, DRUPAL_PREVIEW_HOST, and DRUPAL_PREVIEW_KNOWN_HOSTS. The SSH key has a forced command and restricted permissions. The deployment script is root-owned to prevent modification by the SSH deployment account.

**Rollback**

Previous timestamped releases are retained, making a code rollback possible by restoring an earlier drupal-current symlink target. Database or configuration changes may require a separate recovery procedure, so a symlink rollback alone is not guaranteed to reverse every deployment.