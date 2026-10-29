<?php

/**
 * @file
 * Local integration checks: drush php:script path/to/this/file.
 *
 * Creates temporary users and a match and cleans them up even on failure.
 */

use Drupal\backgammon_matches\Service\MatchResults;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

$users = [];
$match = NULL;
$switcher = \Drupal::service('account_switcher');
$results = \Drupal::service('backgammon_matches.results');
$assert = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new \RuntimeException($message);
  }
  echo "PASS: $message\n";
};
try {
  foreach (['league_player', 'league_player', 'league_player', 'league_admin'] as $index => $role) {
    $user = User::create(['name' => 'bg_test_' . uniqid() . '_' . $index, 'status' => 1]);
    $user->addRole($role);
    $user->save();
    $users[] = $user;
  }
  $switcher->switchTo($users[3]);
  try {
    $match = Node::create([
      'type' => 'backgammon_match', 'title' => 'Temporary match integration check',
      'uid' => $users[3]->id(), 'status' => 1,
      'field_bg_player_1' => $users[0]->id(), 'field_bg_player_2' => $users[1]->id(),
      'field_bg_target' => 3,
    ]);
    $assert($match->validate()->count() === 0, 'Valid fixture passes entity validation');
    $match->save();
    $assert($match->label() === 'Match ' . $match->id(), 'New match title is Match followed by its node ID');
    // Simulate a canonical node route for the displayed page-title hook.
    $request = \Symfony\Component\HttpFoundation\Request::create('/node/' . $match->id());
    $request->attributes->set('_route', 'entity.node.canonical');
    $request->attributes->set('_route_object', new \Symfony\Component\Routing\Route('/node/{node}'));
    $request->attributes->set('node', $match);
    $stack = \Drupal::service('request_stack');
    $stack->push($request);
    try {
      $variables = ['title' => $match->label()];
      backgammon_matches_preprocess_page_title($variables);
      $heading = (string) \Drupal::service('renderer')->renderInIsolation($variables['title']);
      $assert($heading === $users[0]->getDisplayName() . ' v ' . $users[1]->getDisplayName(), 'Page heading displays both competing players');
      $assert(in_array('user:' . $users[0]->id(), $variables['title']['#cache']['tags'], TRUE), 'Heading cache tracks player name changes');
      $assert($match->label() === 'Match ' . $match->id(), 'Displayed heading does not change stored title');
    }
    finally {
      $stack->pop();
    }
    $edit_form = \Drupal::entityTypeManager()->getFormObject('node', 'default')->setEntity($match);
    $built = \Drupal::formBuilder()->getForm($edit_form);
    $assert($built['title']['#access'] === FALSE, 'Title input is hidden on match forms');
    $assert($match->access('update', $users[3]), 'League admin may edit the match');
    $assert(!$match->access('update', $users[0]), 'Participant cannot edit the whole match');
    $assert($results->access($match, $users[0])->isAllowed(), 'Participant may enter the first result');
    $assert(!$results->access($match, $users[2])->isAllowed(), 'Unrelated league player is denied');
    $invalid = clone $match;
    $invalid->set('field_bg_player_2', $users[0]->id());
    $assert($invalid->validate()->count() > 0, 'Duplicate players rejected');
    $invalid = clone $match;
    $invalid->set('field_bg_target', 4);
    $assert($invalid->validate()->count() > 0, 'Unsupported target rejected');
    $extended = clone $match;
    $extended->set('field_bg_target', 15);
    $assert($extended->validate()->count() === 0, 'Extended target accepted');
    $invalid = clone $match;
    $invalid->set('field_bg_score_1', 3);
    $assert($invalid->validate()->count() > 0, 'Partial result rejected');
    $invalid = clone $match;
    $invalid->set('field_bg_score_1', 2)->set('field_bg_score_2', 1);
    $assert($invalid->validate()->count() > 0, 'Winning score below target rejected');
    $invalid = clone $match;
    $invalid->set('field_bg_score_1', 3)->set('field_bg_score_2', 3);
    $assert($invalid->validate()->count() > 0, 'Tied result rejected');
    $invalid = clone $match;
    $invalid->set('field_bg_score_1', 4)->set('field_bg_score_2', 3);
    $assert($invalid->validate()->count() > 0, 'Losing score at the target rejected');
  }
  finally {
    $switcher->switchBack();
  }
  $values = array_fill_keys(MatchResults::RESULT_FIELDS, 0);
  $values['field_bg_score_1'] = 5;
  $switcher->switchTo($users[0]);
  try {
    $controller = \Drupal\backgammon_matches\Controller\MyFixturesController::create(\Drupal::getContainer());
    $fixtures = $controller->build();
    $assert(count($fixtures['other_fixtures']['#rows']) === 1, 'Participant sees their fixture');
    $assert(isset($fixtures['other_fixtures']['#rows'][0][5]['data']['#links']['result']), 'Pending fixture offers result entry');
    $form = \Drupal::formBuilder()->getForm(\Drupal\backgammon_matches\Form\MatchResultForm::class, $match);
    $assert(isset($form['player_1']['result_fields']['field_bg_score_1'], $form['player_2']['result_fields']['field_bg_backgammons_2']), 'Participant result form builds with both players');
    $assert(!isset($form['field_bg_player_1'], $form['field_bg_target']), 'Result form excludes fixture settings');
    $html = (string) \Drupal::service('renderer')->renderInIsolation($form);
    $assert(str_contains($html, 'Save Result'), 'Result form renders successfully');
    $negative = $values;
    $negative['field_bg_gammons_1'] = -1;
    $rejected = FALSE;
    try { $results->saveResult($match, $negative); }
    catch (\InvalidArgumentException $exception) { $rejected = TRUE; }
    $assert($rejected, 'Negative result value rejected');
    $saved = $results->saveResult($match, $values);
    $assert((int) $saved->get('field_bg_score_1')->value === 5, 'Score above target is stored');
    $assert($results->hasResult($saved), 'Zero losing score counts as an entered result');
    $assert(!$results->access($saved, $users[1])->isAllowed(), 'Other participant cannot change saved result');
    $fixtures = $controller->build();
    $assert(!isset($fixtures['other_fixtures']['#rows'][0][5]['data']['#links']['result']), 'Saved result has no player edit action');
    $rejected = FALSE;
    try { $results->saveResult($match, $values); }
    catch (\RuntimeException $exception) { $rejected = TRUE; }
    $assert($rejected, 'Stale fixture cannot bypass result locking');
  }
  finally {
    $switcher->switchBack();
  }
  $switcher->switchTo($users[3]);
  try {
    $values['field_bg_score_1'] = 6;
    $amended = $results->saveResult($match, $values);
    $assert((int) $amended->get('field_bg_score_1')->value === 6, 'League admin may amend saved result');
    $assert($amended->label() === 'Match ' . $amended->id(), 'Result amendment preserves generated title');
  }
  finally {
    $switcher->switchBack();
  }
  $switcher->switchTo($users[2]);
  try {
    $fixtures = \Drupal\backgammon_matches\Controller\MyFixturesController::create(\Drupal::getContainer())->build();
    $assert(count($fixtures['league_fixtures']['#rows']) === 0
      && count($fixtures['knockout_fixtures']['#rows']) === 0
      && !isset($fixtures['other_fixtures']), 'Unrelated player sees no other players fixtures');
  }
  finally {
    $switcher->switchBack();
  }
}
finally {
  if ($match && !$match->isNew()) {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$match->id()]);
    \Drupal::entityTypeManager()->getStorage('node')->load($match->id())?->delete();
  }
  foreach ($users as $user) {
    $user->delete();
  }
}
