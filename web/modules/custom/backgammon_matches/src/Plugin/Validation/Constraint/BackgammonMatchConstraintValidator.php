<?php

namespace Drupal\backgammon_matches\Plugin\Validation\Constraint;

use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/** Applies rules shared by all match consumers. */
class BackgammonMatchConstraintValidator extends ConstraintValidator {

  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof NodeInterface || $value->bundle() !== 'backgammon_match') {
      return;
    }
    $first = $value->get('field_bg_player_1')->target_id;
    $second = $value->get('field_bg_player_2')->target_id;
    if ($first && $first == $second) {
      $this->context->buildViolation('Choose two different players.')
        ->atPath('field_bg_player_2')->addViolation();
    }
    foreach (['field_bg_player_1', 'field_bg_player_2'] as $field) {
      $player = $value->get($field)->entity;
      if ($player && !in_array('league_player', $player->getRoles(), TRUE)) {
        $this->context->buildViolation('Choose a user with the League player role.')
          ->atPath($field)->addViolation();
      }
    }
    $score_1_empty = $value->get('field_bg_score_1')->isEmpty();
    $score_2_empty = $value->get('field_bg_score_2')->isEmpty();
    // Either both scores are empty (a fixture), or both have been entered.
    if ($score_1_empty !== $score_2_empty) {
      $this->context->buildViolation('Enter both player scores or leave both empty.')
        ->atPath('field_bg_score_1')->addViolation();
    }
    elseif (!$score_1_empty) {
      $score_1 = (int) $value->get('field_bg_score_1')->value;
      $score_2 = (int) $value->get('field_bg_score_2')->value;
      $target = (int) $value->get('field_bg_target')->value;
      if ($score_1 === $score_2) {
        $this->context->buildViolation('A completed match must have a winner.')
          ->atPath('field_bg_score_1')->addViolation();
      }
      elseif (max($score_1, $score_2) < $target) {
        $this->context->buildViolation('The winning score must be at least the match target of @target.', ['@target' => $target])
          ->atPath('field_bg_score_1')->addViolation();
      }
      elseif (min($score_1, $score_2) >= $target) {
        $this->context->buildViolation('The losing score must be below the match target of @target.', ['@target' => $target])
          ->atPath('field_bg_score_1')->addViolation();
      }
    }
    // No score cap: a score above the selected target remains valid.
    // Gammon-to-score rules await explicit competition rules.
  }

}
