<?php

/** Integration checks clean up all temporary tournament records and accounts. */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\backgammon_matches\Service\MatchResults;

$users = [];
$tournaments = [];
$switcher = \Drupal::service('account_switcher');
$service = \Drupal::service('backgammon_knockout.brackets');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$matches = static function ($id) use ($storage) {
  return $storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('type', 'backgammon_match')->condition('field_bk_tournament.target_id', $id)->execute());
};
$reload = static function ($id) use ($storage) {
  $storage->resetCache([$id]);
  return $storage->load($id);
};
$assert = static function ($ok, $message) {
  if (!$ok) { throw new \RuntimeException($message); }
  echo "PASS: $message\n";
};
try {
  for ($i = 0; $i < 9; $i++) {
    $user = User::create(['name' => 'bk_test_' . uniqid() . '_' . $i, 'status' => 1]);
    $user->addRole($i === 8 ? 'league_admin' : 'league_player')->save();
    $users[] = $user;
  }
  $switcher->switchTo($users[8]);
  try {
    foreach ([2, 3, 6, 8] as $count) {
      $tournament = Node::create(['type' => 'backgammon_knockout', 'title' => 'Temporary ' . $count . '-player knockout', 'uid' => $users[8]->id(), 'status' => 1, 'field_bk_season' => 1, 'field_bk_start' => '2026-09-01', 'field_bk_end' => '2026-09-21', 'field_bk_target' => 5, 'field_bk_players' => array_map(static fn($u) => $u->id(), array_slice($users, 0, $count))]);
      $assert($tournament->validate()->count() === 0, "$count-player tournament validates");
      $tournament->save();
      $tournaments[] = $tournament;
      $assert($tournament->get('field_bk_status')->value === 'active', 'New knockout defaults to active');
      $assert($tournament->get('field_bk_start')->value . ' - ' . $tournament->get('field_bk_end')->value === '2026-09-01 - 2026-09-21', 'Knockout stores its historical date range');
      $listing = \Drupal\backgammon_knockout\Controller\TournamentsController::create(\Drupal::getContainer())->build();
      $date_ranges = array_map(static fn(array $row): string => (string) $row[1], $listing['tournaments']['#rows']);
      $assert(in_array('2026-09-01 - 2026-09-21', $date_ranges, TRUE), 'Knockout listing displays the historical date range');
      $assert($tournament->label() === 'Knockout Season 1', 'Knockout title generated from season number');
      $edit = \Drupal::entityTypeManager()->getFormObject('node', 'default')->setEntity($tournament);
      $edit_form = \Drupal::formBuilder()->getForm($edit);
      $assert($edit_form['title']['#access'] === FALSE, 'Knockout title input hidden');
      $tournament->set('field_bk_season', 2)->save();
      $assert($tournament->label() === 'Knockout Season 2', 'Title follows changed season number');
      $assert(!$tournament->access('update', $users[0]), 'Player cannot edit tournament');
      $service->draw($tournament);
      if ($count === 2) {
        $knockout_matches = $matches($tournament->id());
        $knockout_match = reset($knockout_matches);
        $match_edit = \Drupal::entityTypeManager()->getFormObject('node', 'default')->setEntity($knockout_match);
        $match_fields = \Drupal::formBuilder()->getForm($match_edit);
        $assert($match_fields['field_bl_season_ref']['#access'] === FALSE
          && ($match_fields['field_bk_tournament']['#access'] ?? TRUE)
          && ($match_fields['field_bk_round']['#access'] ?? TRUE)
          && ($match_fields['field_bk_slot']['#access'] ?? TRUE), 'Knockout match form hides league fields');
      }
      $form = \Drupal::formBuilder()->getForm(\Drupal\backgammon_knockout\Form\DrawForm::class, $tournament);
      $form_html = (string) \Drupal::service('renderer')->renderInIsolation($form);
      $assert(str_contains($form_html, 'assigns random byes'), 'Draw confirmation form renders');
      $tournament = $reload($tournament->id());
      $bracket = json_decode($tournament->get('field_bk_bracket')->value, TRUE);
      $size = 2;
      while ($size < $count) { $size *= 2; }
      $byes = array_filter($bracket['rounds'][0], static fn($slot) => count($slot['players']) === 1);
      $assert(count($byes) === $size - $count, "$count-player draw has correct number of byes");
      $entrants = array_merge(...array_column($bracket['rounds'][0], 'players'));
      $assert(count($entrants) === $count && count(array_unique($entrants)) === $count, 'Each entrant appears once');
      foreach ($byes as $bye) { $assert($bye['winner'] === $bye['players'][0], 'Bye advances automatically'); }
      $original = $tournament->get('field_bk_bracket')->value;
      $service->draw($tournament);
      $assert($reload($tournament->id())->get('field_bk_bracket')->value === $original, 'Repeated draw preserves random bracket');
      if ($count === 2) {
        $ready = $matches($tournament->id());
        $match = reset($ready);
        $values = array_fill_keys(MatchResults::RESULT_FIELDS, 0);
        $rejected = FALSE;
        try {
          \Drupal::service('backgammon_matches.results')->saveResult($match, $values);
        }
        catch (\InvalidArgumentException) {
          $rejected = TRUE;
        }
        $assert($rejected && $reload($tournament->id())->get('field_bk_winner')->isEmpty(), 'Tied score is rejected and never advances a knockout player');
        $values['field_bg_score_1'] = 5;
        \Drupal::service('backgammon_matches.results')->saveResult($match, $values);
      }
      // Play every ready match; result-save hooks must create later rounds.
      for ($iteration = 0; $iteration < $size; $iteration++) {
        $played = 0;
        foreach ($matches($tournament->id()) as $match) {
          if (!\Drupal::service('backgammon_matches.results')->hasResult($match)) {
            $values = array_fill_keys(MatchResults::RESULT_FIELDS, 0);
            $values['field_bg_score_1'] = 5;
            \Drupal::service('backgammon_matches.results')->saveResult($match, $values);
            $played++;
          }
        }
        if (!$played) { break; }
      }
      $tournament = $reload($tournament->id());
      $assert(!$tournament->get('field_bk_winner')->isEmpty(), "$count-player results automatically determine champion");
      $assert(count($matches($tournament->id())) === $count - 1, 'Tournament retains exactly entrants minus one played matches');
      $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($tournament, 'full');
      $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
      $assert(isset($build['knockout_bracket']['#rows'])
        && !isset($build['knockout_bracket']['#caption']), 'Bracket renders without a table caption');
      $assert(!isset($build['field_bk_season'], $build['field_bk_target'], $build['field_bk_status'], $build['field_bk_players'], $build['field_bk_winner']), 'Bracket page hides standard tournament fields');
      $bracket_cells = $build['knockout_bracket']['#rows'][1];
      $assert(count($bracket_cells) === 5
        && in_array('col-4', $bracket_cells[0]['class'], TRUE)
        && in_array('col-1', $bracket_cells[1]['class'], TRUE)
        && in_array('col-2', $bracket_cells[2]['class'], TRUE)
        && in_array('col-1', $bracket_cells[3]['class'], TRUE)
        && in_array('col-4', $bracket_cells[4]['class'], TRUE), 'Bracket matches use the League fixture Bootstrap grid');
      $assert(in_array('text-primary', $bracket_cells[1]['class'], TRUE)
        && in_array('text-primary', $bracket_cells[3]['class'], TRUE), 'Bracket scores use the site primary colour');
      $assert((int) in_array('text-muted', $bracket_cells[0]['class'], TRUE)
        + (int) in_array('text-muted', $bracket_cells[4]['class'], TRUE) === 1, 'Bracket losing player is muted for a result');
    }
    // Changing an earlier winner must preserve existing downstream results.
    $tournament = end($tournaments);
    $first = NULL;
    foreach ($matches($tournament->id()) as $match) {
      if ((int) $match->get('field_bk_round')->value === 1) { $first = $match; break; }
    }
    $values = array_fill_keys(MatchResults::RESULT_FIELDS, 0);
    $values['field_bg_score_2'] = 5;
    \Drupal::service('backgammon_matches.results')->saveResult($first, $values);
    $bracket = json_decode($reload($tournament->id())->get('field_bk_bracket')->value, TRUE);
    $assert(!empty($bracket['conflicts']), 'Earlier winner correction flags downstream fixture conflict');
    $assert(count($matches($tournament->id())) === 7, 'Correction never deletes or duplicates retained matches');
  }
  finally { $switcher->switchBack(); }
  $switcher->switchTo($users[0]);
  try {
    $denied = FALSE;
    try { $service->draw($tournaments[0]); } catch (\RuntimeException $e) { $denied = TRUE; }
    $assert($denied, 'Player cannot generate draws');
  }
  finally { $switcher->switchBack(); }
  $switcher->switchTo($users[8]);
  try {
    foreach ($tournaments as $tournament) {
      $reload($tournament->id())->set('field_bk_status', 'completed')->save();
    }
  }
  finally { $switcher->switchBack(); }
  $switcher->switchTo($users[0]);
  try {
    $fixtures = \Drupal\backgammon_matches\Controller\MyFixturesController::create(\Drupal::getContainer())->build();
    $assert(count($fixtures['knockout_fixtures']['#rows']) === 0, 'Completed knockout fixtures are removed from the player fixtures page');
  }
  finally { $switcher->switchBack(); }
}
finally {
  foreach ($tournaments as $tournament) {
    foreach ($matches($tournament->id()) as $match) { $match->delete(); }
    $reload($tournament->id())?->delete();
  }
  foreach ($users as $user) { $user->delete(); }
}
