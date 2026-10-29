<?php

namespace Drupal\backgammon_matches\Utility;

/**
 * Provides display labels for rounds in a complete knockout bracket.
 */
final class KnockoutRound {

  /**
   * Returns a round label based on its position in the bracket.
   */
  public static function label(int $round, int $round_count): string {
    $rounds_remaining = max(1, $round_count - $round + 1);

    return match ($rounds_remaining) {
      1 => 'Final',
      2 => 'Semi Final',
      3 => 'QTR Final',
      default => 'Round of ' . (2 ** $rounds_remaining),
    };
  }

}
