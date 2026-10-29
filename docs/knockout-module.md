# Knockout Module

## Purpose

The custom `backgammon_knockout` module manages knockout tournaments, creates
random draws and advances winners through the bracket. It depends on
`backgammon_matches`, and every playable fixture is stored as a shared Match
node rather than as knockout-only data.

The Drupal content-type label is **Knockout** and its machine name is
`backgammon_knockout`.

## Knockout fields

- Season number: `field_bk_season`
- Start date: `field_bk_start`
- End date: `field_bk_end`
- Match target: `field_bk_target` (first to 3, 5 or 7)
- Status: `field_bk_status` (`active` or `completed`)
- Players: `field_bk_players`
- Stored bracket: `field_bk_bracket`
- Stored winner: `field_bk_winner`

Match nodes reference their tournament through `field_bk_tournament`. Their
round number and position within that round are stored in `field_bk_round` and
`field_bk_slot`.

Knockout titles are generated automatically in this format:
`Knockout Season 1`. The title input is hidden on the edit form. The end date
must not precede the start date.

## Draw generation

League Admins generate the bracket from the **Generate draw** local task. At
least two distinct League players are required. The initial player order and
first-round slots are randomised, then stored so refreshing the draw does not
shuffle the bracket or replace existing matches. Changing the selected players
afterwards does not redraw an existing bracket.

The bracket size is the next power of two that can contain all entrants.
First-round byes are assigned randomly, with every entrant having an equal
opportunity to receive one. A player with a first-round bye advances
automatically and no Match node is created for the bye. An empty place in a
later round means that an opponent is not known yet; it is not treated as a
bye.

## Results and advancement

Playable fixtures use the shared Matches module's fields, result validation and
permissions. Saving a decisive result advances the winner. The next Match node
is created only when both players in its bracket slot are known.

An equal score does not produce a winner, so the affected branch waits for a
corrected result. The module does not invent a draw or playoff rule. Once the
final has a decisive result, its winner is stored in `field_bk_winner`.

League Admins can correct saved results through the Matches module. If a
correction changes an earlier winner after a downstream match has already been
created, the existing downstream match and result are preserved and the
bracket displays an admin-review warning. No automatic repair is attempted
because the rules for handling those downstream matches have not been defined.

Bracket updates use a tournament-specific lock. If simultaneous updates
contend for that lock, the saved result is retained and advancement is retried
through Drupal cron.

## Completion and history

Marking a tournament **Completed** retains its participants, bracket, matches
and results. Both active and completed tournaments remain available on the
`/knockouts` historical listing, which displays the tournament, date range and
winner.

Fixtures belonging to a completed tournament are removed from participating
players' `/my-fixtures` page.

## Permissions and roles

The module defines `administer backgammon knockouts`. League Admins can create,
edit and delete tournaments. Generating or refreshing a draw requires both
`administer backgammon knockouts` and `administer backgammon matches`. League
players cannot edit tournaments or generate draws. Match result entry and
correction permissions are handled by the shared Matches module.

## Routes

- `/knockouts` — current and historical knockout listing
- `/node/{node}` — tournament bracket and any admin-review warnings
- `/node/{node}/knockout-draw` — confirmation form for generating or refreshing
  the draw

## Main implementation files

- `web/modules/custom/backgammon_knockout/backgammon_knockout.module` — generated
  titles, date validation, access rules, result-triggered advancement and the
  bracket display
- `src/Service/Brackets.php` — random draw creation, byes, match creation,
  advancement, locking and conflict detection
- `src/Controller/TournamentsController.php` — knockout listing
- `src/Form/DrawForm.php` — draw-generation confirmation
- `src/Plugin/QueueWorker/AdvanceBracket.php` — deferred advancement retries
- `backgammon_knockout.install` — content type, fields and update hooks
- `tests/integration.php` — integration checks for draws, byes, advancement,
  permissions, completion and historical behaviour
