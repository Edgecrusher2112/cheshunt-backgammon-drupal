<?php

namespace Drupal\backgammon_league\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/** POST-only generation protects against accidental fixture changes from links. */
class GenerateFixturesForm extends ConfirmFormBase {
  protected NodeInterface $season;

  public static function access(NodeInterface $node, AccountInterface $account) {
    return AccessResult::allowedIf($node->bundle() === 'backgammon_season'
      && $account->hasPermission('administer backgammon leagues')
      && $account->hasPermission('administer backgammon matches'))->cachePerPermissions()->addCacheableDependency($node);
  }
  public function getFormId() { return 'backgammon_league_generate'; }
  public function getQuestion() { return $this->t('Generate missing fixtures for @season?', ['@season' => $this->season->label()]); }
  public function getDescription() { return $this->t('Creates missing meetings using the saved season players and match target. Existing matches and results are preserved.'); }
  public function getCancelUrl() { return Url::fromRoute('entity.node.canonical', ['node' => $this->season->id()]); }
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    $this->season = $node;
    return parent::buildForm($form, $form_state);
  }
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $count = \Drupal::service('backgammon_league.seasons')->generate($this->season);
      $this->messenger()->addStatus($this->t('Created @count missing fixtures.', ['@count' => $count]));
      $form_state->setRedirect('entity.node.canonical', ['node' => $this->season->id()]);
    }
    catch (\RuntimeException | \InvalidArgumentException $exception) {
      $this->messenger()->addError($exception->getMessage());
      $form_state->setRebuild();
    }
  }
}
