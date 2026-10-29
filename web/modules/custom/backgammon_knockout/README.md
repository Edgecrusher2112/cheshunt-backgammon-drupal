# Backgammon Knockout

League Admins create tournaments at `/node/add/backgammon_knockout`, enter the
historical start and end dates, select
the season number, match target (3/5/7/9/11/13/15) and League players, then save and use
**Generate draw**. The draw is stored once; refreshes preserve its ordering.
The title input is hidden; titles are generated as `Knockout Season 1` on each save.
Changing the player selection afterwards does not redraw an existing bracket.

First-round byes are randomly assigned with equal entrant opportunity. The draw
uses the next power-of-two bracket size and never creates a match for a bye.
Future-round slots wait for both advancing players; empty opponents are not byes.

Match records reuse Backgammon Matches fields, validation, permissions and
result entry. They automatically appear on participating players' My fixtures
page. Saving a decisive result advances its winner and creates the next match
when both opponents are known. The final winner is stored as a user reference.
Simultaneous updates that contend for a lock are retried through Drupal cron.

Equal scores and winning scores below the selected target are rejected; no draw
or playoff rule is inferred. League Admins can amend saved results. If an earlier
winner changes after a downstream match already exists, the bracket displays an
admin-review warning and preserves those matches and results. Automatic repair
requires the owner to specify how downstream matches should be handled.

Ongoing and completed tournaments remain at `/knockouts`. Views can use the
tournament season, players and winner fields, and match relationships through
`field_bk_tournament`, `field_bk_round` and `field_bk_slot`. Bracket structure is
stored in `field_bk_bracket` as JSON; no archived tournament is overwritten by a
new tournament. Participants' displayed names follow their current user account.
Set a tournament's status to **Completed** to retain its bracket and results while
removing its fixtures from participating players' My Fixtures pages.

Run local checks with:
`drush php:script web/modules/custom/backgammon_knockout/tests/integration.php`.
Temporary accounts, tournaments and matches are removed after testing.
