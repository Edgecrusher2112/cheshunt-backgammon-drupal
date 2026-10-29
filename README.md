# Cheshunt Backgammon

Drupal site for keeping track of backgammon leagues and knockout in East Herts. Uses Docker4Drupal for local development.

* Read the docs on [**how to use**](https://wodby.com/docs/stacks/drupal/local#usage)

## League
TODO

## Knockout

TODO

# Drupal Deployment Guide

## 1. Overview

Cheshunt Backgammon uses Drupal 11, developed locally using Docker4Drupal and deployed to a Plesk VPS.

Drupal preview deployments are automated using GitHub Actions, SSH and a release-based deployment script.

**Drupal preview:** https://drupal.cheshuntbackgammon.club

**GitHub repository:** `git@github.com:Edgecrusher2112/cheshunt-backgammon-drupal.git`

**Deployment status:**
- Drupal preview: automated and tested successfully.
- Drupal production: not yet configured for automatic deployment.
- Existing Astro staging and production: separate and must remain untouched.

## 2. Git branches

| Branch | Purpose |
|---|---|
| `dev` | Active Drupal development |
| `staging` | Deploys automatically to Drupal preview |
| `master` | Existing branch; not configured for automatic Drupal production deployment |

Normal development workflow:

1. Develop and test changes locally.
2. Export Drupal configuration when necessary.
3. Commit changes to `dev`.
4. Push `dev` to GitHub.
5. Merge approved changes into `staging`.
6. Push `staging` to GitHub.
7. GitHub Actions automatically deploys Drupal preview.
8. Verify the preview website.

Example commands:

```bash
git switch dev
git status
git add .
git commit -m "Describe changes"
git push origin dev

git fetch origin
git switch staging
git merge --ff-only dev
git push origin staging
```

The `--ff-only` merge works when the branches have not diverged. Resolve divergence deliberately rather than forcing the merge.

**Important:** Pushing to `staging` triggers a real deployment.

## 3. VPS directory structure

VPS domain root:

`/var/www/vhosts/cheshuntbackgammon.club`

Drupal preview is located beneath:

`/var/www/vhosts/cheshuntbackgammon.club/staging`

Relevant directories:

```text
cheshuntbackgammon.club/
├── deploy/
│   └── drupal-preview.sh
├── .ssh/
│   ├── id_ed25519_drupal
│   └── id_ed25519_github_actions
└── staging/
    ├── drupal-current -> drupal-releases/<timestamp>
    ├── drupal-releases/
    │   ├── initial/
    │   ├── 20261029142326/
    │   └── 20261029151100/
    └── shared/
        ├── settings.php
        ├── files/
        └── private/
```

Plesk's Drupal preview document root is:

`staging/drupal-current/web`

The `drupal-current` symlink determines which release is served.

Each deployment creates a separate timestamped release directory. Existing releases are retained.

The first successful GitHub Actions deployment was verified using:

`staging/drupal-releases/20261029151100`

This is a historical reference, not a permanent current-release path.

## 4. Persistent Drupal data

The following are shared across releases:

| Resource | Location |
|---|---|
| Drupal settings | `staging/shared/settings.php` |
| Public uploads | `staging/shared/files` |
| Private files | `staging/shared/private` |
| Database | `cbgc_staging` |

The deployment script explicitly links the shared `settings.php` and public `files` directory into each release.

The private files directory exists separately. Its use depends on the Drupal settings configuration; the deployment script does not explicitly create a private-files symlink.

Never replace or delete shared directories during routine deployment.

The staging database is persistent and is not recreated for each release.

## 5. GitHub Actions configuration

Active workflow:

`.github/workflows/deploy-drupal-preview.yml`

The workflow:

- Runs when changes are pushed to `staging`.
- Also declares a manual workflow trigger.
- Uses an Ubuntu GitHub Actions runner.
- Connects to the VPS using SSH.
- Uses strict SSH host-key checking.
- Uses the restricted `drupaldeploy` account.
- Prevents overlapping workflow runs with GitHub Actions concurrency.
- Has a 20-minute job timeout.

Concurrency configuration:

```yaml
concurrency:
  group: drupal-preview
  cancel-in-progress: false
```

The other Drupal workflow files remain disabled:

```text
deploy-drupal-staging.yml.disabled
deploy-drupal-production.yml.disabled
```

Do not activate either without reviewing its purpose and configuration.

## 6. GitHub Actions secrets

Repository location:

**Settings → Secrets and variables → Actions**

Required secrets:

| Secret | Purpose |
|---|---|
| `DRUPAL_PREVIEW_SSH_KEY` | Private SSH key used by GitHub Actions |
| `DRUPAL_PREVIEW_HOST` | VPS hostname |
| `DRUPAL_PREVIEW_KNOWN_HOSTS` | Trusted VPS SSH host key |

Connection details:

```text
Hostname: server.yoga-herts.co.uk
Port: 22
SSH username: drupaldeploy
```

The known-hosts entry identifies the VPS using its ED25519 public host key.

Verified server host-key fingerprint:

`SHA256:rQ7I4wW9CR6fkfR4MuUSg+ywIrf8im2JqkWcFE3kPGc`

The GitHub Actions client-key fingerprint is:

`SHA256:J6YfogaxwNk3wPOJejFIfpy+sywe1sdJ76qjt0TLKvg`

These are different keys serving different purposes.

Never store private keys, passwords or database credentials in Git.

## 7. Restricted SSH deployment account

A dedicated Linux account named `drupaldeploy` was created for incoming GitHub Actions connections.

Its authorised public key is stored in:

`/home/drupaldeploy/.ssh/authorized_keys`

The entry uses SSH restrictions and a forced command:

```text
restrict,command="/usr/bin/sudo -n -u cheshuntbackgammon /var/www/vhosts/cheshuntbackgammon.club/deploy/drupal-preview.sh"
```

The complete entry also includes the SSH public key.

This configuration prevents that key from being used as a normal interactive SSH login. Successful authentication instead invokes the deployment command.

**Warning:** Even a test SSH connection using this key triggers a real deployment.

The corresponding sudoers configuration is:

`/etc/sudoers.d/drupaldeploy`

```text
drupaldeploy ALL=(cheshuntbackgammon) NOPASSWD: /var/www/vhosts/cheshuntbackgammon.club/deploy/drupal-preview.sh
```

This grants permission to run the specified deployment script as `cheshuntbackgammon`, not unrestricted root access.

The VPS deployment script and its parent `deploy` directory are root-owned.

## 8. Separate SSH keys

Two different SSH identities are involved.

**GitHub Actions → VPS**

The dedicated GitHub Actions private key is stored as a GitHub repository secret. Its public key is authorised for `drupaldeploy`.

**VPS → GitHub**

The VPS deployment script uses:

`/var/www/vhosts/cheshuntbackgammon.club/.ssh/id_ed25519_drupal`

This is the dedicated key used to clone the Drupal repository from GitHub.

The two connections should not be confused.

## 9. Drupal preview deployment script

Actual VPS script:

`/var/www/vhosts/cheshuntbackgammon.club/deploy/drupal-preview.sh`

Repository reference:

`scripts/live-deploy/drupal-preview.sh`

The script performs the following operations:

1. Enables strict Bash error handling using `set -euo pipefail`.
2. Selects Plesk PHP 8.3.
3. Defines the Drupal releases, current-release and shared-data paths.
4. Generates a timestamp-based release name.
5. Verifies that `drupal-current` is not an ordinary file or directory.
6. Refuses to proceed if the temporary `drupal-current.new` symlink already exists.
7. Clones the latest `staging` branch into the new release directory.
8. Runs Composer installation without development dependencies.
9. Verifies that shared settings and public files exist.
10. Links the persistent public files and settings into the release.
11. Verifies that Drush is installed.
12. Runs Drupal database updates.
13. Imports Drupal configuration from `config/sync`.
14. Rebuilds caches.
15. Verifies Drupal bootstrap using `drush status`.
16. Creates a temporary symlink to the new release.
17. Atomically replaces `drupal-current` with that symlink.
18. Rebuilds Drupal caches again.
19. Reports successful completion.

The script uses:

```text
PHP: /opt/plesk/php/8.3/bin/php
Composer: /opt/psa/var/modules/composer/composer.phar
```

Composer installation:

```bash
php composer.phar install --no-dev --no-interaction --optimize-autoloader
```

Drush commands:

```bash
drush updb -y
drush cim --source=<release>/config/sync -y
drush cr
drush status
```

The actual script invokes Drush's PHP entry point directly with a 512 MB PHP memory limit, rather than relying on a globally installed Drush executable.

**Important:** Database updates and configuration imports occur before the release symlink changes. These commands operate against the persistent staging database.

The script currently does not implement a deployment-wide lock or automatically clean up failed release directories.

GitHub Actions concurrency prevents overlapping runs of this particular workflow, but does not prevent a separate manual deployment from running simultaneously.

## 10. Verify a deployment

First, inspect the GitHub Actions run:

https://github.com/Edgecrusher2112/cheshunt-backgammon-drupal/actions

Open **Deploy Drupal Preview** and confirm that all steps completed successfully.

Next, check the active release on the VPS:

```bash
readlink -f /var/www/vhosts/cheshuntbackgammon.club/staging/drupal-current
```

The result should point to the expected timestamped directory beneath `staging/drupal-releases`.

Finally, open:

https://drupal.cheshuntbackgammon.club

Confirm that the site loads and the expected changes are present.

A green GitHub Actions result is not a substitute for checking the website.

## 11. Rollback procedure

Previous release directories are retained, so an earlier code release can be reactivated.

**Before rollback:**

1. Identify the currently active release.
2. Identify a known-good previous release.
3. Check whether the deployment performed database updates or configuration imports.
4. Consider taking a database backup.
5. Ensure no other deployment is running.

Inspect releases:

```bash
ls -ld /var/www/vhosts/cheshuntbackgammon.club/staging/drupal-releases/*
```

Check the current release:

```bash
readlink -f /var/www/vhosts/cheshuntbackgammon.club/staging/drupal-current
```

For an approved code-only rollback, the symlink can be switched atomically to a known-good release:

```bash
APP_ROOT="/var/www/vhosts/cheshuntbackgammon.club/staging"
PREVIOUS="$APP_ROOT/drupal-releases/REPLACE_WITH_KNOWN_GOOD_RELEASE"

test -d "$PREVIOUS/web" || exit 1
test -L "$APP_ROOT/drupal-current" || exit 1
test ! -e "$APP_ROOT/drupal-current.new" || exit 1

ln -s "$PREVIOUS" "$APP_ROOT/drupal-current.new"
mv -Tf "$APP_ROOT/drupal-current.new" "$APP_ROOT/drupal-current"
```

Replace the placeholder with an actual verified release directory.

After switching, verify the symlink, rebuild caches using the appropriate Drupal/Plesk PHP and Drush environment, and check the website.

**Critical limitation:** A symlink rollback only changes application code. It does not reverse database schema changes or imported Drupal configuration.

If database changes are incompatible with the previous code, restoring an appropriate database backup may also be necessary.

Database restoration should be planned separately and should never be performed casually on a live site.

## 12. Troubleshooting

**GitHub Actions cannot authenticate**

Check the GitHub SSH secrets, `drupaldeploy` authorised-key entry, SSH permissions and forced-command configuration.

**Host-key verification fails**

Verify the server's current public host key through a trusted channel. Do not disable `StrictHostKeyChecking` merely to bypass the error.

**Git clone fails**

Check the VPS-to-GitHub deploy key, repository access and the `staging` branch.

**Composer fails**

Inspect the Actions output and VPS environment. Confirm Plesk PHP 8.3 and the Composer executable are available.

**Drush fails**

Check the database connection, installed modules, pending database updates and configuration compatibility.

**Temporary symlink already exists**

The deployment script deliberately stops if `drupal-current.new` exists. Investigate why it remains before removing or changing anything.

**Deployment fails before symlink activation**

The previous release should normally remain selected, but database updates or configuration imports may already have occurred. Review the logs and database state before retrying.

**Deployment succeeds but the website fails**

Check the active release, Drupal logs, PHP errors, permissions and database compatibility.

## 13. Production and Astro safeguards

The Drupal preview system is separate from the existing Astro deployments.

Do not modify:

- Astro production document root `httpdocs`.
- Astro staging release directories.
- Astro staging `current` symlink.
- Existing Astro deployment scripts or GitHub Actions workflows.

Drupal preview uses `staging/drupal-current/web` as its document root, not the Astro staging document root.

Drupal production deployment must be planned, configured and tested separately. Do not enable production workflows simply by removing `.disabled` from a filename.

## 14. Verified deployment milestone

On 29 October 2026:

- GitHub Actions successfully completed the first Drupal preview deployment.
- The VPS current-release symlink changed to `staging/drupal-releases/20261029151100`.
- The Drupal preview website was confirmed to load correctly.

This establishes that the initial automated deployment path works end to end.
