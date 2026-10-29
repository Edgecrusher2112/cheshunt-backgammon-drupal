# Match Module

## Purpose

The custom `backgammon_matches` module stores fixtures and results shared by
standalone matches, leagues and knockout tournaments. It provides the common
match fields, validation, result-entry form, permissions and player fixture
listing used by the other competition modules.

The Drupal content-type label is **Match** and its machine name is
`backgammon_match`.

## Match fields

- Player 1: `field_bg_player_1`
- Player 2: `field_bg_player_2`
- Match target: `field_bg_target` (first to 3, 5 or 7)
- Player 1 score: `field_bg_score_1`
- Player 2 score: `field_bg_score_2`
- Player 1 gammons: `field_bg_gammons_1`
- Player 2 gammons: `field_bg_gammons_2`
- Player 1 backgammons: `field_bg_backgammons_1`
- Player 2 backgammons: `field_bg_backgammons_2`

Player references are restricted to users with the League player role. Gammon
and backgammon counts default to zero. Empty scores identify a fixture awaiting
its initial result; zero is a valid entered score.

Stored titles are generated automatically from the node ID, for example
`Match 42`, and the title input is hidden on the edit form. On the match page,
the displayed heading uses the players' current display names in the format
`Player 1 v Player 2`.

## Match validation

A valid match must contain two different League players and a target of 3, 5
or 7 points. Result values must be nonnegative whole numbers, and either both
scores must be entered or both must remain empty.

Scores may exceed the configured match target. The module does not infer a
winner, reject equal scores, connect gammon counts to the score, or add any
result-confirmation rules. Competition-specific behavior is left to the League
and Knockout modules and to explicitly agreed rules.

The shared validation constraint applies to the administrative node form and
the dedicated result form. Code that creates a fixture directly must call the
entity's `validate()` method before saving because Drupal's entity save API does
not run validation automatically.

## Result entry

Participating League players enter an initial result through the **Match
result** local task. The form exposes only the scores, gammons and backgammons;
it does not allow a player to change the participants, target or competition
relationships.

A League player can enter a result only when all of the following are true:

- the match is published;
- they are one of its two participants;
- they have the League player role and `enter backgammon results` permission;
- no result has already been saved.

After the initial result is saved, a League player cannot amend it. A League
Admin can enter or amend a result using the same form. Every result save creates
a new revision recording the current user.

The `backgammon_matches.results` service provides the shared access check,
result detection and save operation used by Matches, League and Knockout. It
reloads each match under a match-specific lock before saving, preventing two
simultaneous initial submissions from overwriting one another.

## Fixtures and competition integration

League and Knockout depend on this module and add their own relationship fields
to Match nodes. The match edit form hides irrelevant relationship fields when a
fixture belongs to one of those competition types.

The `/my-fixtures` page lists published matches involving the current user and
groups them by league season or knockout season, followed by Other Fixtures
when present. League fixtures include their match week, while knockout fixtures
include their bracket round. Each competition heading displays its start and
end dates to show the season timeline. League and Knockout fixtures display one
player and score per line, with their stage and available actions vertically
centred across both lines. Their match targets are omitted because they are
available on the match page.

Fixtures from completed league seasons and knockout tournaments are excluded
from `/my-fixtures`, but their Match nodes and saved results are retained for
competition history.

## Permissions and roles

The module creates or updates the League player and League Admin roles and
defines two permissions:

- `enter backgammon results` — enter the first result for a match in which the
  user participates
- `administer backgammon matches` — create and manage matches and amend saved
  results

Only users with `administer backgammon matches` can create, edit or delete whole
Match nodes. League players use the restricted result form instead of the node
edit form.

## Routes

- `/my-fixtures` — the current League player's active fixtures
- `/node/{node}` — match details and the player-versus-player heading
- `/node/{node}/result` — initial result entry or administrative amendment

## Main implementation files

- `web/modules/custom/backgammon_matches/backgammon_matches.module` — generated
  titles, page headings, access rules, form changes and shared validation setup
- `src/Service/MatchResults.php` — result access, result detection, locking and
  revisioned saves
- `src/Form/MatchResultForm.php` — restricted result-entry form
- `src/Controller/MyFixturesController.php` — grouped player fixture listing
- `src/Plugin/Validation/Constraint/BackgammonMatchConstraintValidator.php` —
  participant and result validation
- `src/Utility/KnockoutRound.php` — shared knockout round labels
- `backgammon_matches.install` — content type, fields, displays and competition
  roles
- `tests/integration.php` — integration checks for validation, permissions,
  result entry, concurrency and fixture listings
