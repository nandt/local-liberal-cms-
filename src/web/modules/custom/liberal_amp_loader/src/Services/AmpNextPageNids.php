<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\liberal_custom_content\Form\LiberalApopseisAdminForm;
use Drupal\liberal_custom_content\Utils\liberalCustomContentUtils;

/**
 * Builds the pool of nids amp-next-page paginates through.
 *
 * Curates 5 homepage regions (home/blackbox/featured/top_stories/apopseis),
 * weight-ordered, advertising articles excluded.
 *
 * Ported from the deleted liberal_node_loader module's GetLoadingNids --
 * its only other consumers (an ajax "infinite scroll" controller and its
 * preload endpoint) were already dead: liberal_theme, the theme that would
 * have rendered the trigger markup, doesn't exist in this codebase anymore.
 */
final readonly class AmpNextPageNids {

  /**
   * Region term id => machine name.
   *
   * @var array<int, string>
   */
  public const array SECTIONS = [
    159 => 'home',
    163 => 'blackbox',
    160 => 'featured',
    3 => 'apopseis',
    162 => 'top_stories',
  ];

  /**
   * Region term id => that region's weight field name.
   *
   * @var array<int, string>
   */
  private const array REGION_WEIGHT_FIELDS = [
    159 => 'field_home_weight',
    163 => 'field_blackbox_weight',
    160 => 'field_featured_weight',
    162 => 'field_top_stories_weight',
  ];

  private const string CACHE_ID = 'infiniteloadregions';

  public function __construct(
    private Connection $database,
    private CacheBackendInterface $cache,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * @return array{without_current_nid: list<int>, with_current_nid: list<int>}
   */
  public function getLoadingNids(int $currentNid): array {
    $regionNids = $this->getRegionNids();
    array_splice($regionNids, 3, 0, [$this->getApopsis()]);

    $loadingNids = [];

    foreach ($regionNids as $region) {
      foreach ($region as $nid) {
        $loadingNids[] = (int) $nid;
      }
    }

    $loadingNids = array_values(array_unique($loadingNids));
    $originalNids = $loadingNids;
    $loadingNids = array_values(array_filter(
      $loadingNids,
      static fn (int $nid): bool => $nid !== $currentNid,
    ));

    return [
      'without_current_nid' => $loadingNids,
      'with_current_nid' => $originalNids,
    ];
  }

  /**
   * @return list<list<int>>
   */
  private function getRegionNids(): array {
    $cache = $this->cache->get(self::CACHE_ID);

    if ($cache !== FALSE) {
      return $cache->data;
    }

    $regionNids = [];
    $cacheTags = [];

    foreach (self::SECTIONS as $regionId => $sectionName) {
      if ($sectionName === 'apopseis') {
        continue;
      }

      $regionNids[] = $this->queryRegion($regionId);
      $cacheTags[] = 'node_loader_region:' . $sectionName;
    }

    foreach ($regionNids as $region) {
      foreach ($region as $nid) {
        $cacheTags[] = 'node:' . $nid;
      }
    }

    $this->cache->set(self::CACHE_ID, $regionNids, Cache::PERMANENT, $cacheTags);

    return $regionNids;
  }

  /**
   * @return list<int>
   */
  private function queryRegion(int $regionId): array {
    $weightField = self::REGION_WEIGHT_FIELDS[$regionId];
    $advertisingTid = liberalCustomContentUtils::getArticleTypeAdvertisingTid();

    $query = $this->database->select('node_field_data', 'nfd');
    $query->join('node__field_article_region', 'fra', 'nfd.nid = fra.entity_id');
    $query->leftJoin(
      'node__field_eidos_arthrou',
      'fia',
      'nfd.nid = fia.entity_id AND fia.field_eidos_arthrou_target_id = :advertising_tid',
      [':advertising_tid' => $advertisingTid],
    );
    $query->join('node__' . $weightField, 'weight', 'nfd.nid = weight.entity_id');

    $query
      ->fields('nfd', ['nid'])
      ->condition('nfd.status', 1)
      ->condition('fra.field_article_region_target_id', $regionId)
      ->isNull('fia.field_eidos_arthrou_target_id')
      ->condition('nfd.type', 'article_liberal')
      ->orderBy('weight.' . $query->escapeField($weightField) . '_value', 'ASC');

    $result = $query->execute();

    if ($result === NULL) {
      return [];
    }

    return array_map(intval(...), array_keys($result->fetchAllAssoc('nid', FetchAs::Associative)));
  }

  /**
   * @return list<int>
   */
  private function getApopsis(): array {
    $config = $this->configFactory->get('liberal_custom_content.apopseis_settings');
    $nids = [];

    for ($i = 1; $i <= LiberalApopseisAdminForm::APOPSEIS_REGIONS; $i++) {
      $status = $config->get('apopseis_region_' . $i . '_fieldset')['apopseis_region_status'] ?? NULL;

      if (!$status) {
        continue;
      }

      $result = views_get_view_result('apopseis_region_' . $i, 'block_1');

      if (!empty($result[0]->nid)) {
        $nids[] = (int) $result[0]->nid;
      }
    }

    return $nids;
  }

}
