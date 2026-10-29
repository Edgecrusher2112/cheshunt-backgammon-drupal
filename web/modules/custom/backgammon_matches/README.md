# Backgammon Matches

Provides the `backgammon_match` node bundle for fixtures and saved results.
Installation creates two single-user references, a required odd-numbered target
from 3 through 15,
two scores, and gammon and backgammon counts for each player. Counts default to
zero; empty scores represent a fixture without a result. A completed result must
have one winner whose score reaches or exceeds the target while the losing score
remains below it. Scores may exceed the target. Match titles are generated from
the node ID (for example, `Match 42`);
the title input is hidden. A temporary title is replaced after the initial insert.

League Admins create matches at `/node/add/backgammon_match` and can edit matches
and results. Assign the League player role to eligible participants. Players use
the **Match result** tab at `/node/{id}/result` to enter their own fixture's first
result. They cannot edit the fixture, enter another player's result or change a
saved result. Existing administrator users also have access through Drupal's
administrator-role permission handling.

League and Knockout modules should depend on this module and reuse the node
bundle and `backgammon_matches.results` service. `saveResult()` checks the current
account and reloads the match under a lock before saving a revision. Entity
validation applies on both normal node forms and result entry. Programmatic
fixture creation must call `$match->validate()` before `$match->save()`;
Drupal's entity save API does not automatically validate entities.

No bye, result-confirmation or gammon-to-score rules are inferred. Those
competition rules require user clarification before implementation.

Configuration exported to `config/sync` includes the fields, displays and roles.
Uninstalling this module does not intentionally delete match content or fields.
