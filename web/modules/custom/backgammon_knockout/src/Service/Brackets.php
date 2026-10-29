<?php

namespace Drupal\backgammon_knockout\Service;

use Drupal\backgammon_matches\Service\MatchResults;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/** Creates persistent random draws and advances winners using shared matches. */
class Brackets {
  public function __construct(
    protected EntityTypeManagerInterface $manager,
    protected LockBackendInterface $lock,
    protected Connection $database,
    protected AccountProxyInterface $account,
    protected MatchResults $results,
  ) {}

  /** Initial draw is generated once; refreshes never shuffle it again. */
  public function draw(NodeInterface $tournament): void {
    if (!$this->account->hasPermission('administer backgammon knockouts')
      || !$this->account->hasPermission('administer backgammon matches')) {
      throw new \RuntimeException('You cannot generate knockout draws.');
    }
    $this->synchronize($tournament->id(), TRUE);
  }

  /**
   * Resolves ready slots under a transaction and tournament-specific lock.
   *
   * Called by saved Match results, not by public GET requests. Empty future
   * slots mean an opponent is unknown, not a bye. Only first-round draw slots
   * explicitly containing one player advance without a match.
   */
  public function synchronize($id, bool $initial = FALSE): void {
    $key = 'backgammon_knockout:' . $id;
    if (!$this->lock->acquire($key, 120)) {
      throw new \RuntimeException('The bracket is being updated. Please try again.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $storage = $this->manager->getStorage('node');
      $storage->resetCache([$id]);
      $tournament = $storage->load($id);
      if (!$tournament || $tournament->bundle() !== 'backgammon_knockout') {
        throw new \InvalidArgumentException('Choose a knockout tournament.');
      }
      $bracket = json_decode($tournament->get('field_bk_bracket')->value ?? '', TRUE) ?: [];
      if (!$bracket) {
        if (!$initial) { return; }
        $players = array_map('intval', array_column($tournament->get('field_bk_players')->getValue(), 'target_id'));
        if (count($players) < 2 || count($players) !== count(array_unique($players)) || $tournament->validate()->count()) {
          throw new \InvalidArgumentException('Add at least two distinct League players and complete the tournament fields.');
        }
        // Fisher-Yates using random_int gives each entrant the same bye chance.
        for ($i = count($players) - 1; $i > 0; $i--) {
          $j = random_int(0, $i);
          [$players[$i], $players[$j]] = [$players[$j], $players[$i]];
        }
        $size = 2;
        while ($size < count($players)) { $size *= 2; }
        $byes = $size - count($players);
        $slots = [];
        for ($i = 0; $i < $size / 2; $i++) {
          $slots[] = ['players' => array_splice($players, 0, $i < $byes ? 1 : 2)];
        }
        for ($i = count($slots) - 1; $i > 0; $i--) {
          $j = random_int(0, $i);
          [$slots[$i], $slots[$j]] = [$slots[$j], $slots[$i]];
        }
        $bracket = ['rounds' => [$slots], 'conflicts' => []];
      }
      $bracket['conflicts'] = [];
      $champion = NULL;
      for ($round = 0; $round < count($bracket['rounds']); $round++) {
        foreach ($bracket['rounds'][$round] as $position => &$slot) {
          $slot['winner'] = NULL;
          if ($round === 0 && count($slot['players']) === 1) {
            $slot['winner'] = $slot['players'][0];
            continue;
          }
          if ($round > 0) {
            $previous = $bracket['rounds'][$round - 1];
            $slot['players'] = [$previous[$position * 2]['winner'] ?? NULL, $previous[$position * 2 + 1]['winner'] ?? NULL];
          }
          if (count(array_filter($slot['players'])) !== 2) {
            if (!empty($slot['match'])) {
              $bracket['conflicts'][] = 'Round ' . ($round + 1) . ', position ' . ($position + 1) . ': an existing match has an unresolved earlier result. Admin review required.';
            }
            continue;
          }
          if (empty($slot['match'])) {
            $match = $storage->create([
              'type' => 'backgammon_match', 'uid' => $tournament->getOwnerId(), 'status' => 1,
              'field_bg_player_1' => $slot['players'][0], 'field_bg_player_2' => $slot['players'][1],
              'field_bg_target' => $tournament->get('field_bk_target')->value,
              'field_bk_tournament' => $id, 'field_bk_round' => $round + 1, 'field_bk_slot' => $position + 1,
            ]);
            if ($match->validate()->count()) { throw new \InvalidArgumentException('A bracket match is invalid. Check participant accounts.'); }
            $match->save();
            $slot['match'] = (int) $match->id();
          }
          $match = $storage->load($slot['match']);
          // Do not silently rewrite downstream fixtures after an admin correction.
          if (!$match || (int) $match->get('field_bg_player_1')->target_id !== (int) $slot['players'][0]
            || (int) $match->get('field_bg_player_2')->target_id !== (int) $slot['players'][1]) {
            $bracket['conflicts'][] = 'Round ' . ($round + 1) . ', position ' . ($position + 1) . ': existing match differs from advancing players. Admin review required.';
            continue;
          }
          if ($this->results->hasResult($match)) {
            $a = (int) $match->get('field_bg_score_1')->value;
            $b = (int) $match->get('field_bg_score_2')->value;
            if ($a !== $b) { $slot['winner'] = $slot['players'][$a > $b ? 0 : 1]; }
          }
        }
        unset($slot);
        $count = count($bracket['rounds'][$round]);
        if ($count === 1) { $champion = $bracket['rounds'][$round][0]['winner']; break; }
        if (!isset($bracket['rounds'][$round + 1])) {
          $bracket['rounds'][$round + 1] = array_fill(0, $count / 2, ['players' => [NULL, NULL]]);
        }
      }
      $encoded = json_encode($bracket, JSON_THROW_ON_ERROR);
      if ($encoded !== $tournament->get('field_bk_bracket')->value || $champion != $tournament->get('field_bk_winner')->target_id) {
        $tournament->set('field_bk_bracket', $encoded)->set('field_bk_winner', $champion);
        $tournament->setNewRevision(TRUE);
        $tournament->setRevisionLogMessage('Knockout bracket generated or advanced.');
        $tournament->save();
      }
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
}
