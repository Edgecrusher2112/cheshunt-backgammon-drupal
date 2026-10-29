<?php

namespace Drupal\backgammon_knockout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;

/** Lists both ongoing and retained completed tournaments. */
class TournamentsController extends ControllerBase {
  public function build(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'backgammon_knockout')->condition('status', 1)->sort('field_bk_season', 'DESC')->sort('nid', 'DESC')->pager(25)->execute();
    $cache = (new CacheableMetadata())->setCacheTags(['node_list:backgammon_knockout', 'node_list', 'user_list'])
      ->setCacheContexts(['user.permissions', 'user.node_grants:view', 'url.query_args']);
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $tournament) {
      $access = $tournament->access('view', NULL, TRUE);
      $cache->addCacheableDependency($access)->addCacheableDependency($tournament);
      if (!$access->isAllowed()) { continue; }
      $winner = $tournament->get('field_bk_winner')->entity;
      if ($winner) { $cache->addCacheableDependency($winner); }
      $start = $tournament->get('field_bk_start')->value;
      $end = $tournament->get('field_bk_end')->value;
      $dates = $start && $end
        ? $this->t('@start - @end', ['@start' => $start, '@end' => $end])
        : '—';
      $rows[] = [['data' => Link::fromTextAndUrl($tournament->label(), $tournament->toUrl())->toRenderable()], $dates, $winner?->getDisplayName() ?? $this->t('Awaiting winner')];
    }
    $build['tournaments'] = ['#type' => 'table', '#header' => [$this->t('Tournament'), $this->t('Dates'), $this->t('Winner')], '#rows' => $rows, '#empty' => $this->t('No knockout tournaments created yet.')];
    $build['pager'] = ['#type' => 'pager'];
    $cache->applyTo($build);
    return $build;
  }
}
