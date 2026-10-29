# Backgammon League

Depends on Backgammon Matches and reuses its node records, access control,
result form, title generation and entity validation.

1. As a League Admin, create a Backgammon League at `/node/add/backgammon_season`.
2. Set its division, season number, start/end dates, meetings per pair
   (1/2/3/4), matches per match week, match target (3/5/7/9/11/13/15) and participants with the League player role.
   The hidden title is generated as `Division 1 – Season 2` on every save.
3. Save, then select **Update fixtures**. The POST form creates missing meetings.
   Fixtures are generated in complete round-robin cycles, so every pairing is
   created once before any pairing repeats. The second cycle reverses player one
   and player two for home and away fixtures; odd-numbered cycles use the
   original order and even-numbered cycles use the reverse order.
4. Add further players by editing the season, then update fixtures again.
   Existing fixture IDs and saved results are preserved; nothing is regenerated
   or deleted. Reducing the meeting count does not remove existing matches.
5. Set the season status to **Completed** when finished. This changes the
   standings tie-break criteria; it does not delete content or impose new
   restrictions on League Admin amendments. Completed seasons remain at `/leagues`.

Each win earns 3 points. Live standings use points, backgammons, then gammons.
Completed standings use points, wins against the tied-point group, backgammons,
then gammons. Equal criteria retain a shared position. Tied match scores and
winning scores below the selected target are rejected. No per-match scheduling
dates are inferred from season dates.

All season metadata and participants are ordinary Drupal fields available to
Views. Matches expose `field_bl_season_ref` as an entity-reference relationship
for filtering by season and joining to its division, number, dates and status.
Standings are calculated from retained match records, not stored as Views fields
or frozen snapshots. User display names follow the current account name.

Season pages display standings and linked matches; players' league fixtures
are labelled with their match week using the season's configured number of
matches per week. They automatically appear on the Matches module's
`/my-fixtures` page.

Run local integration checks with:
`drush php:script web/modules/custom/backgammon_league/tests/integration.php`.
The checks clean up temporary users, seasons and fixtures after completion.
