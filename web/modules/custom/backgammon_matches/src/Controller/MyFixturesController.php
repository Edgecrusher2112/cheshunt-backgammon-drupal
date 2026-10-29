<?php

namespace Drupal\backgammon_matches\Controller;

use Drupal\backgammon_matches\Utility\KnockoutRound;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/** Lists only matches involving the current account, with normal node access. */
class MyFixturesController extends ControllerBase {

  public function build(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', 'backgammon_match')->condition('status', 1);
    $participants = $query->orConditionGroup()
      ->condition('field_bg_player_1.target_id', $this->currentUser()->id())
      ->condition('field_bg_player_2.target_id', $this->currentUser()->id());
    $query->condition($participants);
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldStorageDefinitions('node');
    $has_league_field = isset($field_definitions['field_bl_season_ref']);
    if ($has_league_field) {
      // Keep non-league matches, but remove fixtures belonging to a completed
      // league season before paging is applied.
      if (isset($field_definitions['field_bl_status'])) {
        $active_seasons = $query->orConditionGroup()
          ->notExists('field_bl_season_ref.target_id')
          ->condition('field_bl_season_ref.entity.field_bl_status', 'active');
        $query->condition($active_seasons);
      }
      $query->sort('field_bl_season_ref.target_id', 'DESC')->sort('nid', 'ASC');
    }
    else {
      $query->sort('nid', 'DESC');
    }
    if (isset($field_definitions['field_bk_tournament'], $field_definitions['field_bk_status'])) {
      $active_tournaments = $query->orConditionGroup()
        ->notExists('field_bk_tournament.target_id')
        ->condition('field_bk_tournament.entity.field_bk_status', 'active');
      $query->condition($active_tournaments);
    }
    $ids = $query->pager(25)->execute();
    $results = \Drupal::service('backgammon_matches.results');
    $date_formatter = \Drupal::service('date.formatter');
    $cache = (new CacheableMetadata())
      ->setCacheContexts(['user', 'user.permissions', 'user.node_grants:view', 'url.query_args'])
      ->setCacheTags(['node_list', 'node_list:backgammon_match', 'node_list:backgammon_season', 'node_list:backgammon_knockout']);
    $season_weeks = [];
    $league_groups = [];
    $knockout_groups = [];
    $other_rows = [];
    foreach ($storage->loadMultiple($ids) as $match) {
      $access = $match->access('view', $this->currentUser(), TRUE);
      $cache->addCacheableDependency($access)->addCacheableDependency($match);
      if (!$access->isAllowed()) {
        continue;
      }
      $season = $match->hasField('field_bl_season_ref')
        ? $match->get('field_bl_season_ref')->entity
        : NULL;
      $tournament = $match->hasField('field_bk_tournament')
        ? $match->get('field_bk_tournament')->entity
        : NULL;
      $season_label = $this->t('Other');
      $fixture_stage = NULL;
      if ($season) {
        $cache->addCacheableDependency($season);
        if ($season->hasField('field_bl_status')
          && $season->get('field_bl_status')->value === 'completed') {
          continue;
        }
        $season_label = $season->label();
        if (!isset($season_weeks[$season->id()])) {
          $season_match_ids = array_values($storage->getQuery()
            ->accessCheck(FALSE)
            ->condition('type', 'backgammon_match')
            ->condition('field_bl_season_ref.target_id', $season->id())
            ->sort('nid', 'ASC')
            ->execute());
          $matches_per_week = $season->hasField('field_bl_matches_per_week')
            ? max(1, (int) $season->get('field_bl_matches_per_week')->value)
            : 1;
          foreach ($season_match_ids as $fixture_index => $season_match_id) {
            $season_weeks[$season->id()][$season_match_id] = intdiv($fixture_index, $matches_per_week) + 1;
          }
        }
        $week = $season_weeks[$season->id()][$match->id()] ?? NULL;
        if ($week) {
          $fixture_stage = $this->t('Match Week @week', ['@week' => $week]);
        }
      }
      elseif ($tournament) {
        $cache->addCacheableDependency($tournament);
        if ($tournament->hasField('field_bk_status')
          && $tournament->get('field_bk_status')->value === 'completed') {
          continue;
        }
        $season_label = $this->t('Knockout Season @season', [
          '@season' => $tournament->get('field_bk_season')->value,
        ]);
        $bracket = json_decode($tournament->get('field_bk_bracket')->value ?? '', TRUE) ?: [];
        $round_count = count($bracket['rounds'] ?? []);
        $round = (int) $match->get('field_bk_round')->value;
        if ($round && $round_count) {
          $fixture_stage = KnockoutRound::label($round, $round_count);
        }
      }
      $names = [];
      foreach ([1, 2] as $number) {
        $player = $match->get('field_bg_player_' . $number)->entity;
        if ($player) {
          $cache->addCacheableDependency($player);
        }
        $names[] = $player?->getDisplayName() ?? $this->t('Unknown player');
      }
      $entered = $results->hasResult($match);
      $links = ['view' => [
        'title' => $this->t('View match'),
        'url' => $match->toUrl(),
      ]];
      $result_access = $results->access($match, $this->currentUser());
      $cache->addCacheableDependency($result_access);
      if ($result_access->isAllowed()) {
        $links['result'] = [
          'title' => $entered ? $this->t('Amend result') : $this->t('Enter result'),
          'url' => Url::fromRoute('backgammon_matches.result', ['node' => $match->id()]),
        ];
      }
      $player_links = [
        Link::fromTextAndUrl($names[0], $match->toUrl())->toRenderable(),
        Link::fromTextAndUrl($names[1], $match->toUrl())->toRenderable(),
      ];
      $scores = $entered
        ? [$match->get('field_bg_score_1')->value, $match->get('field_bg_score_2')->value]
        : ['—', '—'];
      $operations = ['#type' => 'operations', '#links' => $links];
      $row = [
        $season_label,
        $fixture_stage ?? '—',
        ['data' => Link::fromTextAndUrl($this->t('@first v @second', ['@first' => $names[0], '@second' => $names[1]]), $match->toUrl())->toRenderable()],
        $match->get('field_bg_target')->value,
        $entered ? $match->get('field_bg_score_1')->value . ' – ' . $match->get('field_bg_score_2')->value : '—',
        ['data' => ['#type' => 'operations', '#links' => $links]],
      ];
      if ($season) {
        $season_id = $season->id();
        if (!isset($league_groups[$season_id])) {
          $start = $season->get('field_bl_start')->value;
          $end = $season->get('field_bl_end')->value;
          $league_groups[$season_id] = [
            'label' => $season->label(),
            'dates' => $start && $end ? $this->t('@start – @end', [
              '@start' => $date_formatter->format(strtotime($start . ' 12:00:00 UTC'), 'custom', 'j F Y', 'UTC'),
              '@end' => $date_formatter->format(strtotime($end . ' 12:00:00 UTC'), 'custom', 'j F Y', 'UTC'),
            ]) : NULL,
            'rows' => [],
          ];
        }
        // The season is displayed once as the group heading. Each fixture uses
        // two table rows so player names and scores remain easy to scan.
        $row_classes = ['fixture-pair'];
        if ((count($league_groups[$season_id]['rows']) / 2) % 2 === 0) {
          $row_classes[] = 'fixture-pair--striped';
        }
        $league_groups[$season_id]['rows'][] = [
          'data' => [
            ['data' => $fixture_stage ?? '—', 'rowspan' => 2, 'class' => ['col-3', 'fw-bold', 'align-middle']],
            ['data' => $player_links[0], 'class' => ['col-5', 'align-middle']],
            ['data' => $scores[0], 'class' => ['col-1', 'text-center', 'fw-bold', 'align-middle']],
            ['data' => $operations, 'rowspan' => 2, 'class' => ['col-3', 'text-end', 'align-middle']],
          ],
          'class' => $row_classes,
        ];
        $league_groups[$season_id]['rows'][] = [
          'data' => [
            ['data' => $player_links[1], 'class' => ['col-5', 'align-middle']],
            ['data' => $scores[1], 'class' => ['col-1', 'text-center', 'fw-bold', 'align-middle']],
          ],
          'class' => $row_classes,
        ];
      }
      elseif ($tournament) {
        $tournament_id = $tournament->id();
        if (!isset($knockout_groups[$tournament_id])) {
          $start = $tournament->get('field_bk_start')->value;
          $end = $tournament->get('field_bk_end')->value;
          $knockout_groups[$tournament_id] = [
            'label' => $tournament->label(),
            'dates' => $start && $end ? $this->t('@start – @end', [
              '@start' => $date_formatter->format(strtotime($start . ' 12:00:00 UTC'), 'custom', 'j F Y', 'UTC'),
              '@end' => $date_formatter->format(strtotime($end . ' 12:00:00 UTC'), 'custom', 'j F Y', 'UTC'),
            ]) : NULL,
            'rows' => [],
          ];
        }
        $row_classes = ['fixture-pair'];
        if ((count($knockout_groups[$tournament_id]['rows']) / 2) % 2 === 0) {
          $row_classes[] = 'fixture-pair--striped';
        }
        $knockout_groups[$tournament_id]['rows'][] = [
          'data' => [
            ['data' => $fixture_stage ?? '—', 'rowspan' => 2, 'class' => ['col-3', 'fw-bold', 'align-middle']],
            ['data' => $player_links[0], 'class' => ['col-5', 'align-middle']],
            ['data' => $scores[0], 'class' => ['col-1', 'text-center', 'fw-bold', 'align-middle']],
            ['data' => $operations, 'rowspan' => 2, 'class' => ['col-3', 'text-end', 'align-middle']],
          ],
          'class' => $row_classes,
        ];
        $knockout_groups[$tournament_id]['rows'][] = [
          'data' => [
            ['data' => $player_links[1], 'class' => ['col-5', 'align-middle']],
            ['data' => $scores[1], 'class' => ['col-1', 'text-center', 'fw-bold', 'align-middle']],
          ],
          'class' => $row_classes,
        ];
      }
      else {
        $other_rows[] = $row;
      }
    }

    if ($league_groups) {
      $build['league_fixtures'] = ['#type' => 'container'];
      foreach ($league_groups as $season_id => $group) {
        $build['league_fixtures']['season_' . $season_id] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['league-fixture-group']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('@season', ['@season' => $group['label']]),
            '#attributes' => ['class' => ['mt-4']],
          ],
          'dates' => [
            '#type' => 'html_tag',
            '#tag' => 'h4',
            '#value' => $group['dates'],
            '#attributes' => ['class' => ['fs-6', 'fw-normal', 'text-muted', 'mb-3']],
            '#access' => (bool) $group['dates'],
          ],
          'table' => [
            '#type' => 'table',
            '#rows' => $group['rows'],
            '#attributes' => ['class' => ['table', 'two-line-fixtures', 'mb-5']],
          ],
        ];
      }
    }
    else {
      $build['league_fixtures'] = [
        '#type' => 'table',
        '#rows' => [],
        '#empty' => $this->t('You have no league fixtures yet.'),
        '#attributes' => ['class' => ['table', 'table-striped']],
      ];
    }
    if ($knockout_groups) {
      $build['knockout_fixtures'] = ['#type' => 'container'];
      foreach ($knockout_groups as $tournament_id => $group) {
        $build['knockout_fixtures']['tournament_' . $tournament_id] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['knockout-fixture-group']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('@tournament', ['@tournament' => $group['label']]),
          ],
          'dates' => [
            '#type' => 'html_tag',
            '#tag' => 'h4',
            '#value' => $group['dates'],
            '#attributes' => ['class' => ['fs-6', 'fw-normal', 'text-muted', 'mb-3']],
            '#access' => (bool) $group['dates'],
          ],
          'table' => [
            '#type' => 'table',
            '#rows' => $group['rows'],
            '#attributes' => ['class' => ['table', 'two-line-fixtures', 'mb-5']],
          ],
        ];
      }
    }
    else {
      $build['knockout_fixtures'] = [
        '#type' => 'table',
        '#rows' => [],
        '#empty' => $this->t('You have no knockout fixtures yet.'),
        '#attributes' => ['class' => ['table', 'two-line-fixtures']],
      ];
    }
    if ($other_rows) {
      $build['other_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Other Fixtures'),
      ];
      $build['other_fixtures'] = [
        '#type' => 'table',
        '#rows' => $other_rows,
        '#attributes' => ['class' => ['table', 'table-striped']],
      ];
    }
    $build['pager'] = ['#type' => 'pager'];
    $cache->applyTo($build);
    return $build;
  }

}
