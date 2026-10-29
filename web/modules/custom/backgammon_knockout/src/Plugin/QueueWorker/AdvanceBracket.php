<?php

namespace Drupal\backgammon_knockout\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Retries advancement when simultaneous submissions contend for the lock. */
#[QueueWorker(id: 'backgammon_knockout_advance', title: new TranslatableMarkup('Advance knockout bracket'), cron: ['time' => 30])]
class AdvanceBracket extends QueueWorkerBase {
  public function processItem($data) {
    // A deleted tournament needs no retry and must not leave an endless job.
    if (!\Drupal::entityTypeManager()->getStorage('node')->load($data['id'])) { return; }
    \Drupal::service('backgammon_knockout.brackets')->synchronize($data['id']);
  }
}
