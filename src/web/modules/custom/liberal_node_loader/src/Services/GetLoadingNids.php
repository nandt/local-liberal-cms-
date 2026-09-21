<?php

namespace Drupal\liberal_node_loader\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\liberal_custom_content\Form\LiberalApopseisAdminForm;
use Drupal\liberal_custom_content\Utils\liberalCustomContentUtils;

class GetLoadingNids {

  /**
   * The database connection used.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  const SECTIONS = [
    159 => "home",
    163 => "blackbox",
    160 => "featured",
    3 => 'apopseis',
    162 => "top_stories",
  ];

  const REGION_WEIGHT_FIELDS = [
    159 => "field_home_weight",
    163 => "field_blackbox_weight",
    160 => "field_featured_weight",
    162 => "field_top_stories_weight",
  ];

  /**
   *  @param array $region_nids
   *  @param integer $current_nid
   *  @return array
  */
  private function removeCurrentNode($region_nids, $current_nid) {
    foreach ($region_nids as $key => $region_nid) {
      if ($region_nid == $current_nid) {
        unset($region_nids[$key]);
      }
    }

    return $region_nids;
  }

  /**
   * @return array;
  */
  private function getApopsis() {
    $apopsis = [];
    $nids = [];

    /* so far i found no better way to check for views that should disabled than
    loading the relevant module config */
    $config_apopsis = \Drupal::config('liberal_custom_content.apopseis_settings');

    for ($i = 1; $i <= LiberalApopseisAdminForm::APOPSEIS_REGIONS; $i++) {
      $status = $config_apopsis->get('apopseis_region_' . $i . '_fieldset')['apopseis_region_status'] ?? NULL;
      if ($status) {
        $apopsis[] = views_get_view_result('apopseis_region_' . $i, 'block_1');
      }
    }

    foreach ($apopsis as $apopsi) {
      if (!empty($apopsi[0]->nid)) {
        $nids[] = $apopsi[0]->nid;
      }
    }

    return [$nids];
  }

  public function getLoadingNids($current_nid) {

    $cache_id_region = 'infiniteloadregions';

    if ($cache = \Drupal::cache()->get($cache_id_region)) {
      $region_nids = $cache->data;
    }
    else {
      foreach (self::SECTIONS as $preload_region => $section_name) {
        if ($section_name != 'apopseis') {
          $preload_weight = self::REGION_WEIGHT_FIELDS[$preload_region];
          $advertising_article_id = liberalCustomContentUtils::getArticleTypeAdvertisingTid();
          $query = $this->connection->select('node_field_data', 'nfd');

          $query->join('node__field_article_region', 'fra', 'nfd.nid = fra.entity_id');
          $query->leftJoin('node__field_eidos_arthrou', 'fia', 'nfd.nid = fia.entity_id AND fia.field_eidos_arthrou_target_id = :advertising_article_id',
          [':advertising_article_id' => $advertising_article_id]);
          $query->join('node__' . $preload_weight, 'weight', 'nfd.nid = weight.entity_id');

          $query
            ->fields('nfd', ['nid'])
            ->condition('nfd.status', 1)
            ->condition('fra.field_article_region_target_id', $preload_region)
            ->isNull('fia.field_eidos_arthrou_target_id')
            ->condition('nfd.type', "article_liberal")
            ->orderBy('weight.' . $query->escapeField($preload_weight) . '_value', 'ASC');

          $region_nids[] = array_keys($query->execute()->fetchAllAssoc('nid', \Drupal\Core\Database\Statement\FetchAs::Associative));

          // add a default tag even if the results are empty so that it can be cleared
          $cache_tags_region[] = 'node_loader_region:' . $section_name;
        }
      }

      foreach ($region_nids as $regions) {
        foreach ($regions as $rnid) {
          $cache_tags_region[] = 'node:' . $rnid;
        }
      }

      \Drupal::cache()
        ->set(
            $cache_id_region,
            $region_nids,
            CacheBackendInterface::CACHE_PERMANENT,
            $cache_tags_region
        );
    }

    // concatenate apopsis before removing current node
    $apopsis = $this->getApopsis();
    array_splice($region_nids, 3, 0, $apopsis);

    //flatten array
    foreach ($region_nids as $region) {
      foreach ($region as $rnid) {
        $loading_nids[] = (int) $rnid;
      }
    }

    // remove duplicate records
    $loading_nids = array_unique($loading_nids);

    // reset keys because it might cause issues in js
    $loading_nids = array_values($loading_nids);

    // save original array before removing current node
    $original_nids = $loading_nids;

    // remove current
    $loading_nids = $this->removeCurrentNode($loading_nids, $current_nid);

    return [
      'without_current_nid' => $loading_nids,
      'with_current_nid' => $original_nids,
    ];
  }

}
