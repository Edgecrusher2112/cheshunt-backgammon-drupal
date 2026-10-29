<?php

namespace Drupal\backgammon_knockout\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/** CSRF-protected generation or refresh; existing draws never get rerandomised. */
class DrawForm extends ConfirmFormBase {
  protected NodeInterface $tournament;
  public static function access(NodeInterface $node, AccountInterface $account) {
    return AccessResult::allowedIf($node->bundle() === 'backgammon_knockout'
      && $account->hasPermission('administer backgammon knockouts')
      && $account->hasPermission('administer backgammon matches'))->cachePerPermissions()->addCacheableDependency($node);
  }
  public function getFormId() { return 'backgammon_knockout_draw'; }
  public function getQuestion() { return $this->t('Generate or refresh the draw for @tournament?', ['@tournament' => $this->tournament->label()]); }
  public function getDescription() { return $this->t('The first draw assigns random byes and advances those players automatically. Refreshing preserves the existing draw and matches.'); }
  public function getCancelUrl() { return Url::fromRoute('entity.node.canonical', ['node' => $this->tournament->id()]); }
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    $this->tournament = $node;
    return parent::buildForm($form, $form_state);
  }
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      \Drupal::service('backgammon_knockout.brackets')->draw($this->tournament);
      $this->messenger()->addStatus($this->t('The knockout bracket has been updated.'));
      $form_state->setRedirect('entity.node.canonical', ['node' => $this->tournament->id()]);
    }
    catch (\RuntimeException | \InvalidArgumentException $exception) {
      $this->messenger()->addError($exception->getMessage());
      $form_state->setRebuild();
    }
  }
}
