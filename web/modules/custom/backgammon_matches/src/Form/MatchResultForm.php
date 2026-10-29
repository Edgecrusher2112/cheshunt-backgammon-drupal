<?php

namespace Drupal\backgammon_matches\Form;

use Drupal\backgammon_matches\Service\MatchResults;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Provides result-only entry without exposing fixture settings to players. */
class MatchResultForm extends FormBase {

  protected NodeInterface $match;

  public function __construct(protected MatchResults $results) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('backgammon_matches.results'));
  }

  /** Route access and save access share one implementation. */
  public static function access(NodeInterface $node, AccountInterface $account) {
    return \Drupal::service('backgammon_matches.results')->access($node, $account);
  }

  public function getFormId() {
    return 'backgammon_matches_result_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    $this->match = $node;
    $form['#attributes']['class'][] = 'backgammon-match-result-form';
    $form['notice'] = ['#markup' => '<p>' . $this->t('League players can enter a result once. Only League Admins can amend saved results.') . '</p>'];
    foreach ([1, 2] as $number) {
      $player = $node->get('field_bg_player_' . $number)->entity;
      $form['player_' . $number] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Player @number: @name', ['@number' => $number, '@name' => $player?->getDisplayName() ?? '']),
        '#attributes' => ['class' => ['backgammon-result-player', 'mb-4']],
      ];
      $form['player_' . $number]['result_fields'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-3']],
      ];
      foreach (['score' => 'Score', 'gammons' => 'Gammons', 'backgammons' => 'Backgammons'] as $suffix => $label) {
        $field = 'field_bg_' . $suffix . '_' . $number;
        $form['player_' . $number]['result_fields'][$field] = [
          '#type' => 'number',
          '#title' => $label,
          '#min' => 0,
          '#step' => 1,
          '#required' => TRUE,
          '#default_value' => $node->get($field)->value,
          '#wrapper_attributes' => ['class' => ['col-12', 'col-md-4']],
        ];
      }
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Result'),
      '#button_type' => 'primary',
      '#attributes' => ['data-match-result-submit' => 'true'],
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate a copy: showing errors must never mutate the stored match.
    $candidate = clone $this->match;
    foreach (MatchResults::RESULT_FIELDS as $field) {
      $candidate->set($field, $form_state->getValue($field));
    }
    foreach ($candidate->validate() as $violation) {
      $form_state->setErrorByName(explode('.', $violation->getPropertyPath())[0], $violation->getMessage());
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = [];
    foreach (MatchResults::RESULT_FIELDS as $field) {
      $values[$field] = $form_state->getValue($field);
    }
    try {
      $match = $this->results->saveResult($this->match, $values);
      $this->messenger()->addStatus($this->t('The match result has been saved.'));
      $form_state->setRedirect('entity.node.canonical', ['node' => $match->id()]);
    }
    catch (\RuntimeException | \InvalidArgumentException $exception) {
      // A result may have been entered after this form was opened.
      $this->messenger()->addError($exception->getMessage());
      $form_state->setRebuild();
    }
  }

}
