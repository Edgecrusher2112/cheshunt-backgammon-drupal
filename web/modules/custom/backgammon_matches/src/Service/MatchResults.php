<?php

namespace Drupal\backgammon_matches\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Shared result-entry API for Matches, League and Knockout.
 */
class MatchResults {

  /** The only fields participants can submit through result entry. */
  public const RESULT_FIELDS = [
    'field_bg_score_1', 'field_bg_score_2',
    'field_bg_gammons_1', 'field_bg_gammons_2',
    'field_bg_backgammons_1', 'field_bg_backgammons_2',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LockBackendInterface $lock,
    protected AccountProxyInterface $currentUser,
  ) {}

  /** Both scores must exist before a result is considered entered; zero is valid. */
  public function hasResult(NodeInterface $match): bool {
    return !$match->get('field_bg_score_1')->isEmpty() && !$match->get('field_bg_score_2')->isEmpty();
  }

  /** Access varies by account, permissions and the match's saved result. */
  public function access(NodeInterface $match, AccountInterface $account): AccessResult {
    $allowed = FALSE;
    if ($match->bundle() === 'backgammon_match') {
      $admin = $account->hasPermission('administer backgammon matches');
      $participant = in_array((int) $account->id(), [
        (int) $match->get('field_bg_player_1')->target_id,
        (int) $match->get('field_bg_player_2')->target_id,
      ], TRUE);
      $allowed = $admin || ($account->isAuthenticated()
        && in_array('league_player', $account->getRoles(), TRUE)
        && $account->hasPermission('enter backgammon results')
        && $participant && !$this->hasResult($match) && $match->isPublished());
    }
    return AccessResult::allowedIf($allowed)
      ->addCacheContexts(['user', 'user.permissions', 'user.roles'])
      ->addCacheableDependency($match);
  }

  /**
   * Saves a result against a fresh record, preventing two initial submissions.
   *
   * Permissions are always checked against the current account. Competition
   * modules should call this API rather than accept arbitrary node edits.
   */
  public function saveResult(NodeInterface $match, array $values): NodeInterface {
    $key = 'backgammon_match_result:' . $match->id();
    if (!$this->lock->acquire($key, 30)) {
      throw new \RuntimeException('This match is being updated. Please try again.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $storage->resetCache([$match->id()]);
      $fresh = $storage->load($match->id());
      if (!$fresh || !$this->access($fresh, $this->currentUser)->isAllowed()) {
        throw new \RuntimeException('You cannot enter or change this match result.');
      }
      foreach (self::RESULT_FIELDS as $field) {
        if (!array_key_exists($field, $values) || !preg_match('/^\d+$/D', (string) $values[$field])) {
          throw new \InvalidArgumentException('Enter a nonnegative whole number for each result field.');
        }
        $fresh->set($field, $values[$field]);
      }
      $violations = $fresh->validate();
      if ($violations->count()) {
        throw new \InvalidArgumentException((string) $violations->get(0)->getMessage());
      }
      $fresh->setNewRevision(TRUE);
      $fresh->setRevisionUserId($this->currentUser->id());
      $fresh->setRevisionCreationTime(time());
      $fresh->setRevisionLogMessage('Match result entered or amended.');
      $fresh->save();
      return $fresh;
    }
    finally {
      $this->lock->release($key);
    }
  }

}
