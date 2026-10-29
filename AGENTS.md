# Cheshunt Backgammon

## Site Colours

This is the primary and secondary colours for the site.

- Primary #EECDA4
- Secondary #5A2B03

### User Roles

- Authenticated: just registered but not playing in the league
- League player: a player that can take part in matches, leagues and knockouts and enter results only for matches they are involved in. They cannot change a result once it has been saved.
- League Admin: can create matches, leagues and knockouts and also edit them.
- Participating League Players can enter the initial result; only League Admins can change saved results.

### Match, League & Knockout Modules

Create three custom Drupal modules:

- Backgammon Matches: stores matches and handles results.
- Backgammon League: creates round-robin fixtures and league standings.
- Backgammon Knockout: creates brackets and advances winners.

League and Knockout must depend on the Matches module and reuse
its match records, forms, permissions, and result validation.

### Match Module

- Player 1 v Player 2
- First to 3, 5, 7, 9, 11, 13 or 15 points (store the score if it's more than this still)
- Match score
- Only participating League Players or League Admins can enter the initial result.
- Number of gammons in match per player
- Number of backgammons in match per player
- Can then be used with the League or Knockout module
- Results can't be changed by League players once they're entered, only by League admin

### League Module

This will create a round robin with a start and end date and with 3 points for a win.

- Division number
- Season number
- Players involved (will be using league player role)
- Number of times players play each other 1, 2, 3, 4
- Standings are calculated on total points
- Tied standings are decided by number of backgammons then gammons
- Tied final position is decided by head to head results then by backgammons then gammons
- Head-to-head compares wins only in matches between the players tied on total points.
- Players can be added to an existing season. Updating fixtures creates only missing meetings and preserves existing fixtures and results.
- Completed seasons retain their participants, matches and results for historical Views.

### Knockout Module

This will create a random draw based on how many players are involved.

- Season number
- Players involved: will be using league player role
- Winner progresses to next round
- First-round byes are assigned randomly and advance those players automatically.

### Undecided Rules

Ask me before implementing any competition rules not specified here.
Do not invent tie-break rules, bye allocation, result confirmation,
or restrictions on changing completed results.

## Drupal deployment instructions for agents

This repository is the Cheshunt Backgammon Drupal project.

Read the **Drupal Deployment Guide** in `README.md` before making changes involving deployment, hosting, GitHub Actions, Drupal configuration or database updates.

### Deployment architecture

- Development branch: `dev`.
- Automated Drupal preview branch: `staging`.
- Drupal preview URL: `https://drupal.cheshuntbackgammon.club`.
- GitHub Actions workflow: `.github/workflows/deploy-drupal-preview.yml`.
- VPS deployment script: `/var/www/vhosts/cheshuntbackgammon.club/deploy/drupal-preview.sh`.
- Repository deployment-script reference: `scripts/live-deploy/drupal-preview.sh`.
- Active Drupal release symlink: `staging/drupal-current`.
- Timestamped releases: `staging/drupal-releases/`.
- Persistent data: `staging/shared/`.
- Staging database: `cbgc_staging`.

### Mandatory safeguards

1. Never push to `staging`, activate a workflow or initiate a deployment without explicit user approval.
2. Never deploy to Drupal production without explicit authorisation and an established production procedure.
3. Never modify the existing Astro staging or production deployments.
4. Never delete or overwrite persistent Drupal settings, uploaded files or database data.
5. Never commit private SSH keys, credentials or secrets.
6. Do not weaken SSH restrictions, sudo permissions or host-key verification.
7. Treat Drupal database updates and configuration imports as potentially irreversible without a suitable backup.
8. Preserve existing deployment releases unless their removal has been explicitly approved.
9. Review the current Git branch and working-tree status before making changes.
10. Do not assume a successful GitHub Actions run guarantees a healthy website; verify the deployed release and Drupal preview URL.

### Deployment behaviour

Pushing to `staging` automatically starts the Drupal preview workflow.

The workflow connects to the VPS using the restricted `drupaldeploy` account. Its forced SSH command runs the root-owned deployment script as `cheshuntbackgammon`.

The script clones `staging`, installs Composer dependencies, links persistent Drupal resources, runs database updates and configuration imports, verifies Drupal bootstrap, switches the active release symlink and rebuilds caches.

Database updates and configuration imports occur before the symlink switch. A failed deployment may therefore affect the persistent database even if the previous code release remains active.

### Working practices

- Prefer developing and committing on `dev`.
- Inspect changes before merging into `staging`.
- Use `git merge --ff-only dev` only when the branch history permits it.
- Never force-push or reset shared branches without explicit approval.
- Keep deployment changes small and reviewable.
- Document changes to infrastructure or deployment procedures in `README.md`.
- Ask for approval before performing rollback or destructive maintenance.

