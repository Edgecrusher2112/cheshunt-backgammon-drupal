# League Module

## Purpose

The custom `backgammon_league` module manages league seasons, generates
round-robin fixtures and calculates live and final standings. It depends on
`backgammon_matches`, and every generated fixture is stored as a shared Match
node rather than as league-only data.

The Drupal content-type label is **League** and its machine name is
`backgammon_season`.

## League fields

- Division number: `field_bl_division`
- Season number: `field_bl_season`
- Start date: `field_bl_start`
- End date: `field_bl_end`
- Meetings per pair: `field_bl_meetings` (1, 2 or 3)
- Matches per match week: `field_bl_matches_per_week`
- Match target: `field_bl_target` (first to 3, 5 or 7)
- Status: `field_bl_status` (`active` or `completed`)
- Players: `field_bl_players`
- Stored winner: `field_bl_winner`
- Stored runner-up: `field_bl_runner_up`

Match nodes reference their league through `field_bl_season_ref`.

League titles are generated automatically in this format:
`Division 1 – Season 1`. The title input is hidden on the edit form.

## Fixture generation

League Admins generate fixtures from the **Update fixtures** local task. The
generator creates only missing meetings, so existing fixtures and saved results
are preserved when players are added or the meeting count is increased.

Fixtures are created in complete round-robin cycles. Everyone plays once before
any pairing repeats. For a second meeting, the player order is reversed to give
home and away fixtures. A third meeting returns to the original order.

The configured matches-per-week value groups fixtures into Match Week 1, Match
Week 2 and so on. The season page displays fixtures by match week.

## Standings and ranking

A win awards **3 league points**. Equal match scores do not award points because
the project has no draw-points rule.

Live standings for an active season are sorted by:

1. Total league points
2. Backgammons
3. Gammons

Final standings for a completed season are sorted by:

1. Total league points
2. Head-to-head wins between players tied on total league points
3. Backgammons
4. Gammons

Head-to-head is a mini-table: it counts wins only in matches between players in
the tied-points group. If every confirmed criterion is still equal, the players
retain the same position; the module does not invent another tie-break rule.

When a season is marked **Completed**, unique first and second places are stored
as Winner and Runner-up. These values refresh if a League Admin corrects a saved
result. A position left unresolved by all tie-breakers is not assigned
arbitrarily.

## Completion and history

Completing a season does not delete its participants, fixtures or results. The
season remains available on the `/leagues` historical listing, which displays
the season, date range, winner and runner-up.

Fixtures belonging to a completed season are removed from participating
players' `/my-fixtures` page.

## Permissions and roles

The module defines `administer backgammon leagues`. League Admins can create and
edit seasons and generate missing fixtures. League players cannot edit the
season or generate fixtures. Match result entry and correction permissions are
handled by the shared Matches module.

## Routes

- `/leagues` — current and historical league listing
- `/node/{node}` — league standings and grouped fixtures
- `/node/{node}/generate-fixtures` — confirmation form for generating missing
  fixtures

## Main implementation files

- `web/modules/custom/backgammon_league/backgammon_league.module` — form hooks,
  generated titles, access rules and season-page tables
- `src/Service/Seasons.php` — fixture generation, standings and stored placings
- `src/Controller/SeasonsController.php` — league listing
- `src/Form/GenerateFixturesForm.php` — fixture-generation confirmation
- `backgammon_league.install` — content type, fields and update hooks
- `tests/integration.php` — integration checks for fixtures, ranking, permissions,
  completion and historical behaviour
