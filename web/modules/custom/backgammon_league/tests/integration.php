<?php

/** Local integration checks with temporary seasons, users and matches. */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\backgammon_matches\Service\MatchResults;

$users = [];
$season = NULL;
$switcher = \Drupal::service('account_switcher');
$service = \Drupal::service('backgammon_league.seasons');
$assert = static function ($value, $message) {
  if (!$value) { throw new \RuntimeException($message); }
  echo "PASS: $message\n";
};
try {
  foreach (['league_player', 'league_player', 'league_player', 'league_player', 'league_admin'] as $index => $role) {
    $user = User::create(['name' => 'bl_test_' . uniqid() . '_' . $index, 'status' => 1]);
    $user->addRole($role)->save();
    $users[] = $user;
  }
  $switcher->switchTo($users[4]);
  try {
    $season = Node::create([
      'type' => 'backgammon_season', 'title' => 'Temporary league integration season', 'uid' => $users[4]->id(), 'status' => 1,
      'field_bl_division' => 1, 'field_bl_season' => 1, 'field_bl_start' => '2026-01-01', 'field_bl_end' => '2026-12-31',
      'field_bl_meetings' => 1, 'field_bl_matches_per_week' => 2, 'field_bl_target' => 5,
      'field_bl_players' => [$users[0]->id(), $users[1]->id(), $users[2]->id()],
    ]);
    $assert($season->validate()->count() === 0, 'Season passes entity validation');
    $season->save();
    $assert($season->label() === 'Division 1 – Season 1', 'Season title is generated from division and season');
    $listing = \Drupal\backgammon_league\Controller\SeasonsController::create(\Drupal::getContainer())->build();
    $date_ranges = array_map(static fn(array $row): string => (string) $row[1], $listing['seasons']['#rows']);
    $assert(in_array('2026-01-01 - 2026-12-31', $date_ranges, TRUE), 'League listing displays the historical date range');
    $assert($season->access('update', $users[4]), 'League admin may edit season');
    $assert(!$season->access('update', $users[0]), 'League player cannot edit season');
    $assert($service->generate($season) === 3, 'Three players generate three pairings');
    $assert($service->generate($season) === 0, 'Repeated generation creates no duplicates');
    $league_matches = $service->matches($season);
    $league_match = reset($league_matches);
    $match_edit = \Drupal::entityTypeManager()->getFormObject('node', 'default')->setEntity($league_match);
    $match_fields = \Drupal::formBuilder()->getForm($match_edit);
    $assert(($match_fields['field_bl_season_ref']['#access'] ?? TRUE)
      && $match_fields['field_bk_tournament']['#access'] === FALSE
      && $match_fields['field_bk_round']['#access'] === FALSE
      && $match_fields['field_bk_slot']['#access'] === FALSE, 'League match form hides knockout fields');
    $original_ids = array_keys($service->matches($season));
    $season->set('field_bl_players', array_map(static fn($u) => $u->id(), array_slice($users, 0, 4)))->save();
    $assert($service->generate($season) === 3, 'Fourth player adds only three missing pairings');
    $assert(count(array_intersect($original_ids, array_keys($service->matches($season)))) === 3, 'Original fixture IDs preserved');
    $form = \Drupal::formBuilder()->getForm(\Drupal\backgammon_league\Form\GenerateFixturesForm::class, $season);
    $form_html = (string) \Drupal::service('renderer')->renderInIsolation($form);
    $assert(str_contains($form_html, 'Creates missing meetings') && isset($form['actions']['submit']), 'Fixture update confirmation form renders');
    $edit_form = \Drupal::entityTypeManager()->getFormObject('node', 'default')->setEntity($season);
    $fields = \Drupal::formBuilder()->getForm($edit_form);
    $assert(isset($fields['field_bl_players'], $fields['field_bl_division']), 'Season form provides participants and division');
    $assert($fields['title']['#access'] === FALSE, 'Season title input is hidden');
    $season->set('field_bl_division', 2)->set('field_bl_season', 3)->save();
    $assert($season->label() === 'Division 2 – Season 3', 'Season title updates when its numbers change');
    // A beats B, B beats C, C beats A and D: A/B tie on points, A wins head-to-head.
    $wins = [[$users[0]->id(), $users[1]->id()], [$users[1]->id(), $users[2]->id()], [$users[2]->id(), $users[0]->id()], [$users[2]->id(), $users[3]->id()]];
    foreach ($service->matches($season) as $match) {
      $a = $match->get('field_bg_player_1')->target_id;
      $b = $match->get('field_bg_player_2')->target_id;
      foreach ($wins as [$winner, $loser]) {
        if (in_array($a, [$winner, $loser]) && in_array($b, [$winner, $loser])) {
          $values = array_fill_keys(MatchResults::RESULT_FIELDS, 0);
          $values['field_bg_score_1'] = $a == $winner ? 5 : 0;
          $values['field_bg_score_2'] = $b == $winner ? 5 : 0;
          // B has more backgammons, allowing us to distinguish live/final ordering.
          $values['field_bg_backgammons_1'] = $a == $users[1]->id() ? 2 : 0;
          $values['field_bg_backgammons_2'] = $b == $users[1]->id() ? 2 : 0;
          \Drupal::service('backgammon_matches.results')->saveResult($match, $values);
        }
      }
    }
    $rows = $service->standings($season);
    $assert($rows[0]['uid'] === (int) $users[2]->id() && $rows[0]['points'] === 6, 'Wins award three points');
    $assert(array_sum(array_column($rows, 'losses')) === 4, 'Each decided match adds one loss to the losing player');
    $assert($rows[1]['uid'] === (int) $users[1]->id(), 'Active standings use backgammons before gammons');
    $season->set('field_bl_status', 'completed')->save();
    $rows = $service->standings($season);
    $assert($rows[1]['uid'] === (int) $users[0]->id(), 'Final standings prioritise tied-player head-to-head');
    $assert((int) $season->get('field_bl_winner')->target_id === (int) $users[2]->id()
      && (int) $season->get('field_bl_runner_up')->target_id === (int) $users[0]->id(), 'Completed season stores its winner and runner-up');
    // Remove one result temporarily to leave A/B/C tied on points. Their
    // circular wins should all count once within the three-player mini-table.
    foreach ($service->matches($season) as $match) {
      if ($match->get('field_bg_player_2')->target_id == $users[3]->id()
        && $match->get('field_bg_player_1')->target_id == $users[2]->id()) {
        $match->set('field_bg_score_1', NULL)->set('field_bg_score_2', NULL)->save();
        $tied = array_filter($service->standings($season), static fn($r) => $r['points'] === 3);
        $assert(count($tied) === 3 && array_sum(array_column($tied, 'head')) === 3, 'Three-player tie uses only tied-group matches');
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$season->id()]);
        $tied_season = \Drupal::entityTypeManager()->getStorage('node')->load($season->id());
        $assert((int) $tied_season->get('field_bl_winner')->target_id === (int) $users[1]->id(), 'Completed-season result changes refresh all final tie-breakers');
        $values = array_fill_keys(MatchResults::RESULT_FIELDS, 0);
        $values['field_bg_score_1'] = 5;
        \Drupal::service('backgammon_matches.results')->saveResult($match, $values);
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$season->id()]);
        $season = \Drupal::entityTypeManager()->getStorage('node')->load($season->id());
        $assert((int) $season->get('field_bl_winner')->target_id === (int) $users[2]->id(), 'Completed-season result changes refresh the league winner');
      }
    }
    $assert(count($service->matches($season)) === 6, 'Completing season retains all fixtures and results');
    $season->set('field_bl_meetings', 2)->save();
    $assert($service->generate($season) === 6, 'Two meetings creates one extra fixture per pair');
    $two_meeting_matches = array_values($service->matches($season));
    $second_cycle = array_slice($two_meeting_matches, 6, 6);
    foreach ($second_cycle as $match) {
      $pair = [
        (int) $match->get('field_bg_player_1')->target_id,
        (int) $match->get('field_bg_player_2')->target_id,
      ];
      $opposite = array_filter(
        array_slice($two_meeting_matches, 0, 6),
        static fn($first_match) =>
          (int) $first_match->get('field_bg_player_1')->target_id === $pair[1]
          && (int) $first_match->get('field_bg_player_2')->target_id === $pair[0],
      );
      $assert(count($opposite) === 1, 'Second-cycle fixture reverses the home and away players');
    }
    $season->set('field_bl_meetings', 3)->save();
    $assert($service->generate($season) === 6, 'Three meetings creates the remaining fixture per pair');
    $season->set('field_bl_meetings', 4)->save();
    $assert($service->generate($season) === 6, 'Four meetings creates a fourth fixture per pair');
    $assert($service->generate($season) === 0, 'Updated round robin remains idempotent');
    $assert($service->standings($season)[0]['points'] === 6, 'Fixture updates preserve scored results');
    $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($season, 'full');
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $assert(!isset($build['league_standings']['#caption'])
      && !isset($build['league_matches']['#caption']), 'Season page renders standings and fixtures without table captions');
    $assert(in_array('fw-bold', $build['league_standings']['#header'][2]['class'], TRUE)
      && in_array('text-primary', $build['league_standings']['#rows'][0][0]['class'], TRUE)
      && in_array('fw-bold', $build['league_standings']['#rows'][0][1]['class'], TRUE)
      && in_array('fw-bold', $build['league_standings']['#rows'][0][2]['class'], TRUE)
      && in_array('fw-bold', $build['league_standings']['#rows'][0][7]['class'], TRUE), 'Standings emphasise headers, players, played and points');
    $fixture_cells = $build['league_matches']['#rows'][1];
    $assert(count($fixture_cells) === 5 && !is_array($fixture_cells[0]['data']), 'Season fixtures display as five unlinked cells');
    $assert(in_array('text-end', $fixture_cells[0]['class'], TRUE)
      && in_array('text-center', $fixture_cells[1]['class'], TRUE)
      && in_array('text-center', $fixture_cells[2]['class'], TRUE)
      && in_array('text-center', $fixture_cells[3]['class'], TRUE)
      && in_array('text-start', $fixture_cells[4]['class'], TRUE), 'Fixture names, scores and separator use the requested alignment');
    $assert(in_array('col-4', $fixture_cells[0]['class'], TRUE)
      && in_array('col-1', $fixture_cells[1]['class'], TRUE)
      && in_array('col-2', $fixture_cells[2]['class'], TRUE)
      && in_array('col-1', $fixture_cells[3]['class'], TRUE)
      && in_array('col-4', $fixture_cells[4]['class'], TRUE), 'Fixture cells use the complete twelve-column Bootstrap grid');
    $assert(in_array('fw-bold', $fixture_cells[0]['class'], TRUE)
      && in_array('fw-bold', $fixture_cells[2]['class'], TRUE)
      && in_array('fw-bold', $fixture_cells[4]['class'], TRUE), 'Fixture player names and separator are bold');
    $assert(in_array('text-primary', $fixture_cells[1]['class'], TRUE)
      && in_array('text-primary', $fixture_cells[3]['class'], TRUE), 'Fixture scores use the site primary colour');
    $assert((int) in_array('text-muted', $fixture_cells[0]['class'], TRUE)
      + (int) in_array('text-muted', $fixture_cells[4]['class'], TRUE) === 1, 'Fixture losing player is muted');
    $assert(substr_count($html, 'Match Week 1') === 1 && str_contains($html, 'Match Week 2'), 'Fixtures are grouped by match week');
    $season->set('field_bl_status', 'active')->save();
  }
  finally { $switcher->switchBack(); }
  $switcher->switchTo($users[0]);
  try {
    $my_fixtures = \Drupal\backgammon_matches\Controller\MyFixturesController::create(\Drupal::getContainer())->build();
    $fixture_weeks = array_map(static function (array $row): int {
      preg_match('/\d+/', (string) $row[1], $matches);
      return (int) ($matches[0] ?? 0);
    }, $my_fixtures['league_fixtures']['#rows']);
    $sorted_weeks = $fixture_weeks;
    sort($sorted_weeks);
    $assert($fixture_weeks && $fixture_weeks === $sorted_weeks, 'Player fixtures are displayed in ascending match-week order');
    $denied = FALSE;
    try { $service->generate($season); }
    catch (\RuntimeException $exception) { $denied = TRUE; }
    $assert($denied, 'Player cannot generate fixtures');
  }
  finally { $switcher->switchBack(); }
  $switcher->switchTo($users[4]);
  try {
    $season->set('field_bl_status', 'completed')->save();
  }
  finally { $switcher->switchBack(); }
  $switcher->switchTo($users[0]);
  try {
    $my_fixtures = \Drupal\backgammon_matches\Controller\MyFixturesController::create(\Drupal::getContainer())->build();
    $assert(count($my_fixtures['league_fixtures']['#rows']) === 0, 'Completed season fixtures are removed from the player fixtures page');
  }
  finally { $switcher->switchBack(); }
}
finally {
  if ($season && !$season->isNew()) {
    foreach ($service->matches($season) as $match) { $match->delete(); }
    $season->delete();
  }
  foreach ($users as $user) { $user->delete(); }
}
