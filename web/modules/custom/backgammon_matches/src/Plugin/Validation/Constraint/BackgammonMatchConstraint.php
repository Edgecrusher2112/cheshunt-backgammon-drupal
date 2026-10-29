<?php

namespace Drupal\backgammon_matches\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/** Validates match participants and completeness without inventing tie rules. */
#[Constraint(
  id: 'BackgammonMatch',
  label: new TranslatableMarkup('Backgammon match', [], ['context' => 'Validation']),
  type: 'entity:node',
)]
class BackgammonMatchConstraint extends SymfonyConstraint {}
