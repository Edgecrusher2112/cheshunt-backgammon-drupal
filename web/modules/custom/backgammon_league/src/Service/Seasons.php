<?php

namespace Drupal\backgammon_league\Service;

use Drupal\backgammon_matches\Service\MatchResults;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/** Shared fixture and standings calculations; seasons never replace old seasons. */
class Seasons {

  public function __construct(
    protected EntityTypeManagerInterface $manager,
    protected LockBackendInterface $lock,
    protected Connection $database,
    protected AccountProxyInterface $account,
    protected MatchResults $results,
  ) {}

  /** Loads all season matches for calculations, not as an access bypass for links. */
  public function matches(NodeInterface $season): array {
    $storage = $this->manager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'backgammon_match')
      ->condition('field_bl_season_ref.target_id', $season->id())->sort('nid')->execute();
    return $storage->loadMultiple($ids);
  }

  /** Creates missing pairings only, so added participants preserve existing results. */
  public function generate(NodeInterface $season): int {
    if (!$this->account->hasPermission('administer backgammon leagues')
      || !$this->account->hasPermission('administer backgammon matches')) {
      throw new \RuntimeException('You cannot generate league fixtures.');
    }
    $key = 'backgammon_league:' . $season->id();
    if (!$this->lock->acquire($key, 120)) {
      throw new \RuntimeException('Fixtures are being updated. Try again.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $storage = $this->manager->getStorage('node');
      $storage->resetCache([$season->id()]);
      $season = $storage->load($season->id());
      if (!$season || $season->bundle() !== 'backgammon_season') {
        throw new \InvalidArgumentException('Choose a league season.');
      }
      if ($season->validate()->count()) {
        throw new \InvalidArgumentException('Correct the season fields before generating fixtures.');
      }
      $players = array_unique(array_column($season->get('field_bl_players')->getValue(), 'target_id'));
      if (count($players) < 2) {
        throw new \InvalidArgumentException('Add at least two players to the season.');
      }
      // An unordered pair key makes repeated updates independent of player order.
      $pairs = [];
      foreach ($this->matches($season) as $match) {
        $pair = [(int) $match->get('field_bg_player_1')->target_id, (int) $match->get('field_bg_player_2')->target_id];
        sort($pair);
        $key_pair = implode(':', $pair);
        $pairs[$key_pair] = ($pairs[$key_pair] ?? 0) + 1;
      }
      $created = 0;
      $meetings = (int) $season->get('field_bl_meetings')->value;
      $seen = [];
      // Finish a complete round robin before starting the next meeting cycle.
      // Alternating cycles reverse player one/player two to give home and away
      // fixtures when two meetings are configured.
      foreach ($this->fixturePlan(array_values($players), $meetings) as [$first, $second]) {
        $pair = [(int) $first, (int) $second];
        sort($pair);
        $key_pair = implode(':', $pair);
        $occurrence = $seen[$key_pair] ?? 0;
        $seen[$key_pair] = $occurrence + 1;
        if ($occurrence < ($pairs[$key_pair] ?? 0)) {
          continue;
        }
        $match = $storage->create([
          'type' => 'backgammon_match', 'uid' => $this->account->id(), 'status' => 1,
          'field_bg_player_1' => $first, 'field_bg_player_2' => $second,
          'field_bg_target' => $season->get('field_bl_target')->value,
          'field_bl_season_ref' => $season->id(),
        ]);
        // Reuse Matches entity validation, fields, title generation and result API.
        if ($match->validate()->count()) {
          throw new \InvalidArgumentException('A generated match is invalid. Check the season players.');
        }
        $match->save();
        $created++;
      }
      return $created;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    finally {
      unset($transaction);
      $this->lock->release($key);
    }
  }

  /**
   * Builds complete round-robin cycles in their intended fixture order.
   *
   * Even-numbered cycles use the original player order. Odd-numbered cycles
   * reverse each pairing, making the second meeting the away fixture.
   */
  private function fixturePlan(array $players, int $meetings): array {
    $pairings = [];
    foreach ($players as $index => $first) {
      foreach (array_slice($players, $index + 1) as $second) {
        $pairings[] = [(int) $first, (int) $second];
      }
    }

    $fixtures = [];
    for ($meeting = 0; $meeting < $meetings; $meeting++) {
      foreach ($pairings as [$first, $second]) {
        $fixtures[] = $meeting % 2 === 0
          ? [$first, $second]
          : [$second, $first];
      }
    }
    return $fixtures;
  }

  /** Calculates live or final standings from retained match records. */
  public function standings(NodeInterface $season): array {
    $table = [];
    $ensure = static function ($uid) use (&$table): void {
      $table[$uid] ??= ['uid' => (int) $uid, 'played' => 0, 'wins' => 0, 'losses' => 0, 'points' => 0, 'backgammons' => 0, 'gammons' => 0, 'head' => 0];
    };
    foreach ($season->get('field_bl_players') as $item) {
      $ensure($item->target_id);
    }
    $decided = [];
    foreach ($this->matches($season) as $match) {
      // Include past participants too, preserving their historical contributions.
      $first = (int) $match->get('field_bg_player_1')->target_id;
      $second = (int) $match->get('field_bg_player_2')->target_id;
      $ensure($first);
      $ensure($second);
      if (!$this->results->hasResult($match)) {
        continue;
      }
      foreach ([1 => $first, 2 => $second] as $number => $uid) {
        $table[$uid]['played']++;
        $table[$uid]['backgammons'] += (int) $match->get('field_bg_backgammons_' . $number)->value;
        $table[$uid]['gammons'] += (int) $match->get('field_bg_gammons_' . $number)->value;
      }
      $a = (int) $match->get('field_bg_score_1')->value;
      $b = (int) $match->get('field_bg_score_2')->value;
      // Equal scores do not establish a winner; no draw points are invented.
      if ($a === $b) {
        continue;
      }
      $winner = $a > $b ? $first : $second;
      $loser = $a > $b ? $second : $first;
      $table[$winner]['wins']++;
      $table[$loser]['losses']++;
      $table[$winner]['points'] += 3;
      $decided[] = [$first, $second, $winner];
    }
    // Mini-table: wins only against opponents tied on total season points.
    foreach ($decided as [$first, $second, $winner]) {
      if ($table[$first]['points'] === $table[$second]['points']) {
        $table[$winner]['head']++;
      }
    }
    $criteria = $season->get('field_bl_status')->value === 'completed'
      ? ['points', 'head', 'backgammons', 'gammons'] : ['points', 'backgammons', 'gammons'];
    $rows = array_values($table);
    usort($rows, static function ($a, $b) use ($criteria) {
      foreach ($criteria as $field) {
        if ($a[$field] !== $b[$field]) {
          return $b[$field] <=> $a[$field];
        }
      }
      return 0;
    });
    $previous = NULL;
    foreach ($rows as $index => &$row) {
      $key = array_map(static fn($field) => $row[$field], $criteria);
      // If all confirmed criteria tie, retain the tie rather than invent a winner.
      $row['position'] = $key === $previous ? $rows[$index - 1]['position'] : $index + 1;
      $previous = $key;
    }
    unset($row);
    return $rows;
  }

  /**
   * Stores unique first and second places for a completed season.
   */
  public function assignPlacings(NodeInterface $season): bool {
    $winner = NULL;
    $runner_up = NULL;
    if ($season->get('field_bl_status')->value === 'completed') {
      $standings = $this->standings($season);
      $first = array_values(array_filter($standings, static fn(array $row): bool => $row['position'] === 1));
      $second = array_values(array_filter($standings, static fn(array $row): bool => $row['position'] === 2));
      $winner = count($first) === 1 ? $first[0]['uid'] : NULL;
      $runner_up = count($second) === 1 ? $second[0]['uid'] : NULL;
    }

    $changed = (int) $season->get('field_bl_winner')->target_id !== (int) $winner
      || (int) $season->get('field_bl_runner_up')->target_id !== (int) $runner_up;
    $season->set('field_bl_winner', $winner);
    $season->set('field_bl_runner_up', $runner_up);
    return $changed;
  }

}
