<?php

namespace Drupal\backgammon_league\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Cache\CacheableMetadata;

/** Keeps both active and completed seasons discoverable. */
class SeasonsController extends ControllerBase {
  public function build(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'backgammon_season')->condition('status', 1)->sort('field_bl_season', 'DESC')->sort('field_bl_division')->pager(25)->execute();
    $cache = (new CacheableMetadata())->setCacheContexts(['user.permissions', 'user.node_grants:view', 'url.query_args'])->setCacheTags(['node_list:backgammon_season', 'node_list']);
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $season) {
      $access = $season->access('view', NULL, TRUE);
      $cache->addCacheableDependency($season)->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $winner = $season->get('field_bl_winner')->entity;
        $runner_up = $season->get('field_bl_runner_up')->entity;
        if ($winner) {
          $cache->addCacheableDependency($winner);
        }
        if ($runner_up) {
          $cache->addCacheableDependency($runner_up);
        }
        $rows[] = [
          ['data' => Link::fromTextAndUrl($season->label(), $season->toUrl())->toRenderable(), 'class' => ['col-12', 'col-md-4']],
          ['data' => [
            'start' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => $this->t('<strong>Start:</strong> @date', ['@date' => $season->get('field_bl_start')->value]),
            ],
            'end' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => $this->t('<strong>End:</strong> @date', ['@date' => $season->get('field_bl_end')->value]),
            ],
          ], 'class' => ['col-md-4', 'd-none', 'd-md-table-cell']],
          ['data' => $winner?->getDisplayName() ?? '—', 'class' => ['col-md-2', 'd-none', 'd-md-table-cell']],
          ['data' => $runner_up?->getDisplayName() ?? '—', 'class' => ['col-md-2', 'd-none', 'd-md-table-cell']],
        ];
      }
    }
    $build['seasons'] = [
      '#type' => 'table',
      '#attributes' => ['class' => ['align-middle']],
      '#header' => [
        ['data' => $this->t('Season'), 'class' => ['col-12', 'col-md-4']],
        ['data' => $this->t('Dates'), 'class' => ['col-md-4', 'd-none', 'd-md-table-cell']],
        ['data' => $this->t('Winner'), 'class' => ['col-md-2', 'd-none', 'd-md-table-cell']],
        ['data' => $this->t('Runner-Up'), 'class' => ['col-md-2', 'd-none', 'd-md-table-cell']],
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No seasons created yet.'),
    ];
    $build['pager'] = ['#type' => 'pager'];
    $cache->applyTo($build);
    return $build;
  }
}
