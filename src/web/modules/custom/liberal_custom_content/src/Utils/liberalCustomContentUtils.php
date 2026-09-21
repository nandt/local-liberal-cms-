<?php

namespace Drupal\liberal_custom_content\Utils;

use Drupal\Core\Cache\Cache;
use Drupal\node\NodeInterface;

class liberalCustomContentUtils {

  /**
   * Homepage region machine names, for node_loader_region:* cache tags.
   */
  const nodeLoaderRegions = ['home', 'blackbox', 'featured', 'apopseis', 'top_stories'];

  const taxonomyLiberalHasChildren = [
    6 => "ΟΙΚΟΝΟΜΙΑ",
    9 => "ΑΓΟΡΕΣ",
    70 => "ΑΠΟΨΗ",
  ];

  const readMoreBulletValues = 2;

  const readMoreOnlyInViewModes = [
    'full',
    'amp',
  ];

  const printImpressionsImageViewModes = [
    'read_more',
  ];

  const feedPagesViewIds = [
    'news_feed_page',
    'markets_feed_page',
  ];

  const regionVocabularies = [
    'regions',
    'liberal_markets_regions',
    'oblation_regions',
  ];

  // Update as necessary with new tags
  const regionsCacheTags = [
    'taxonomy_term_list:regions',
    'taxonomy_term_list:liberal_markets_regions',
    'taxonomy_term_list:oblation_regions',
  ];

  const regionExceptions = [
    'liberal_market_main_articles' => 'lm_main_articles',
    'liberal_market_top_stories' => 'lm_top_stories',
    'ob_zone_a' => 'oblation_zone_a',
    'ob_zone_b' => 'oblation_zone_b',
  ];

  /** List here all the regions of vid Regions that are produced
   * with LiberalCustomViewsBlock.
   */
  const regionCacheExceptions = [
    'Stoixima',
  ];

  /**
   * List all fields that correspond to taxonomies that need to be cleared on save
   */
  const taxonomiesClearCache = [
    'field_liberal_category' => "category",
    'field_liberal_tags' => "tags",
    'field_arthrografos' => "author",
  ];

  const teleytaiaEnimerosiFormat = 'H:i';

  const regionsCacheId = 'liberalregionsweight';

  const articleTypesCacheId = 'advertisingarticletypeid';

  const oblationTypeCacheId = 'liberaloblationtype';

  const oblationTypeCacheTags = [
    'taxonomy_term_list:oblation_type',
  ];

  public static function addLiberalCssAssets(&$page, $path_info, $theme = NULL) {

    // Get the current path
    $current_path = $path_info['current_path'];
    $is_front = $path_info['is_front'];
    $route_name = $path_info['route_name'];

    /**
     * Add CSS files to their pages
    */

    $node = \Drupal::routeMatch()->getParameter('node') instanceof NodeInterface ? \Drupal::routeMatch()->getParameter('node') : NULL;

    // Liberal article and Liberal Markets article pages
    if (isset($node) && $node->getType() == "article_liberal") {
      $page['#attached']['library'][] = 'liberal_theme/articles';
    }

    // Liberal frontpage, Liberal Markets frontpage and live-stock-market pages
    elseif ($is_front || str_starts_with((string) $current_path, '/liberal-markets') || str_starts_with((string) $current_path, '/live-stock-market')) {
      $page['#attached']['library'][] = 'liberal_theme/frontpage';
    }

    // Taxonomy and search pages
    elseif (
    str_starts_with((string) $current_path, '/taxonomy/term/') ||
    $route_name == 'view.search_solr.page_1' ||
    $route_name == 'view.podcasts_page.page_1') {
      $page['#attached']['library'][] = 'liberal_theme/taxonomy';
    }

    // libraries for stocks internal pages
    elseif (str_starts_with((string) $current_path, '/live-ase-market')) {
      $page['#attached']['library'][] = 'liberal_theme/xrimatistirio';
    }

    // Contact page
    elseif (str_starts_with((string) $current_path, '/contact/epikoinonia')) {
      $page['#attached']['library'][] = 'liberal_theme/contact-page';
    }

    // 404 page, etc
    elseif ($route_name == 'system.404' || $route_name == 'system.403') {
      $page['#attached']['library'][] = 'liberal_theme/error';
    }

    // Oblation page
    elseif ($route_name == 'view.oblation_microsite.page') {
      $page['#attached']['library'][] = 'liberal_theme/oblations';
    }

    // /news-feed, /markets-feed pages
    elseif ($route_name == 'liberal_custom_content.news_feed'
      || $route_name == 'liberal_custom_content.markets_feed') {
      $page['#attached']['library'][] = 'liberal_theme/news-feed';
    }

    elseif (isset($node) && $node->getType() == "page") {
      $page['#attached']['library'][] = 'liberal_theme/static-pages';
    }

    elseif (isset($node) && $node->getType() == "podcast") {
      $page['#attached']['library'][] = 'liberal_theme/podcast';
    }

    elseif ($route_name == 'user.login'
        || $route_name == 'entity.user.canonical') {
      $page['#attached']['library'][] = 'liberal_theme/static-pages';
    }
  }

  /**
   * Return the formatted last updated date
   * @param string $created
   * Date in the form of d/m/Y
   * @param string $last_updated
   * Date in the form of d/m/Y
   * @return boolean
   */
  public static function isSimpleFormatLastUpdated($created, $last_updated) {
    if ($created === $last_updated) {
      $simple_format = TRUE;
    }
    else {
      $simple_format = FALSE;
    }

    return $simple_format;
  }

  /**
   * Invalidate Custom Regions Tags
   * @param array $regions
   * @param string $region_name
   * @return void
   */
  public static function invalidateRegionsCache($regions, $region_name) {

    if (isset($regions)) {
      // invalidate all regions the node belongs to
      foreach ($regions as $region) {
        $custom_tag = 'region_block_views:' . $region;
        $tags[] = $custom_tag;
      }

      if (in_array($region_name, self::nodeLoaderRegions, TRUE)) {
        $tags[] = "node_loader_region:" . $region_name;
      }

      Cache::invalidateTags($tags);
    }
  }

  /**
   * Clear Apopseis frontpage cache
   * @param string $author_category
   * @return void
   */
  public static function clearApopseisCache($author_category) {
    $authors_config = \Drupal::config('liberal_custom_content.apopseis_settings');
    $authors_config_data = $authors_config->get();

    foreach ($authors_config_data as $key => $data) {
      // author is in the frontpage on an enabled block
      if ($data['apopseis_region'] == $author_category && !empty($data['apopseis_region_status'])) {
        $tag_key = str_replace('_fieldset', '', $key);
        Cache::invalidateTags(['liberal_front_authors:' . $tag_key]);

        // break from the loop because an article can have only 1 author
        break;
      }
    }
  }

  /**
   * Construct array for clearing cache
   * ATTENTION. We do not check for the values before the node edit here.
   * We can but since this is ran on save it will overcomplicate the saves.
   * For previous values, the pages will get updated because of the existing node:XXX tags
   * @param NodeInterface $node
   * @return array
   */
  public static function createTaxonomiesCacheTags(NodeInterface $node): array {
    $result = [];

    foreach (self::taxonomiesClearCache as $field => $value) {
      $categories[$value] = $node->get($field)->getValue();
    }

    // flatten array and prepare it
    foreach ($categories as $category) {
      if (!empty($category)) {
        foreach ($category as $tag) {
          $result[] = 'liberal_taxonomy_page:' . $tag['target_id'];
        }
      }
    }

    return $result;
  }

  /**
   * Clear Taxonomy (Category page & feeds ) cache
   * @param array $categories
   * @return void
   */
  public static function clearTaxonomies($categories) {
    Cache::invalidateTags($categories);
  }

  /**
   * @return non-empty-array<array{tid: mixed, name: mixed, vid: mixed, weight: mixed}>[]
   */
  public static function getRegionsWeightsMapping(): array {
    $regions = [];
    $connection = \Drupal::database();

    // General query for all uses
    $query = $connection->select('taxonomy_term_field_data', 'ttfd');
    $query->condition('status', 1, '=')
      ->condition('vid', self::regionVocabularies, 'IN')
      ->fields('ttfd', ['tid', 'name', 'vid'])
      ->orderBy('tid', 'ASC');

    $results = $query->execute();
    $results = $results->fetchAllAssoc('tid', \Drupal\Core\Database\Statement\FetchAs::Associative);

    // associate our approximation with a true field
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getFieldDefinitions('node', "article_liberal");

    // weight fields are generally in the lower part, reverse to make searching faster
    $fields = array_reverse(array_keys($fields));

    // group by vid
    foreach ($results as $result) {
      $regions[$result['vid']][$result['tid']] = [
        'tid' => $result['tid'],
        'name' => $result['name'],
        'vid' => $result['vid'],
        'weight' => self::getFieldWeightName($result['name'], $fields),
      ];
    }

    // set the cache
    \Drupal::cache()
      ->set(
          self::regionsCacheId,
          $regions,
          Cache::PERMANENT,
          self::regionsCacheTags
      );

    return $regions;
  }

  /**
   * Get all oblation types names mapped to their id
   */
  public static function getOblationTypesMappingName() {
    $connection = \Drupal::database();

    $query = $connection->select('taxonomy_term_field_data', 'ttfd');
    $query->condition('status', 1, '=')
      ->condition('vid', 'oblation_type', 'IN')
      ->fields('ttfd', ['tid', 'name'])
      ->orderBy('name', 'ASC');

    $results = $query->execute();

    // get results by name. Should have unique names in this case
    $results = $results->fetchAllKeyed(1, 0);

    // revert again to tid mapping while preserving order
    foreach ($results as $key => $value) {
      // Set the value as the key in the new array
      $tid_results[$value] = $key;
    }

    // set the cache
    \Drupal::cache()
      ->set(
          self::oblationTypeCacheId,
          $tid_results,
          Cache::PERMANENT,
          self::oblationTypeCacheTags
      );

    return $tid_results;
  }

  /**
   * From now on please name future weight fields as field_[NAME OF TAXONOMY]
   */
  public static function getFieldWeightName($name, $fields) {
    $weight_field = "field_";
    $name = str_replace('-', '_', $name);
    $name = str_replace(' ', '_', $name);
    $name = strtolower($name);
    $name = self::regionExceptions[$name] ?? $name;
    $weight_field .= $name;

    foreach ($fields as $field) {
      if (str_starts_with((string) $field, $weight_field)) {
        $weight_field = $field;
        break;
      }
    }

    return $weight_field;
  }

  /**
   * @param array $regions
   * @return array
   */
  public static function mapRegionArray($regions): array {
    $mapped_regions = [];

    foreach ($regions as $vid) {
      foreach ($vid as $key => $region) {
        $mapped_regions[$key] = [
          'name' => $region['name'],
          'weight' => $region['weight'],
          'cache_name' => strtolower(str_replace([" ", "-"], '_', $region['name'])),
          'vid' => $region['vid'],
        ];
      }
    }

    return $mapped_regions;
  }

  /**
   *  @param NodeInterface $node
   *  @return boolean
   *
   * */
  public static function checkIfNodeIsAdvertising(NodeInterface $node) {
    $is_advertising_article = FALSE;

    if ($node->getType() == 'article_liberal') {
      $advertising_article_id = self::getArticleTypeAdvertisingTid();
      $article_types = $node->get('field_eidos_arthrou')->getValue();

      if (!empty($article_types)) {
        $article_types = array_column($node->get('field_eidos_arthrou')->getValue(), 'target_id');
      }

      if (in_array($advertising_article_id, $article_types)) {
        $is_advertising_article = TRUE;
      }
    }

    return $is_advertising_article;
  }

  public static function getArticleTypeAdvertisingTid() {

    if ($cache = \Drupal::cache()->get(self::articleTypesCacheId)) {
      $tid = $cache->data;
    }
    else {
      // General query for all uses
      $connection = \Drupal::database();

      $query = $connection->select('taxonomy_term_field_data', 'ttfd');
      $query->condition('status', 1)
        ->condition('vid', 'eidi_arthron')
        ->condition('name', 'Διαφημιστικό Άρθρο')
        ->fields('ttfd', ['tid', 'name']);

      $results = $query->execute();
      $results = $results->fetchAllKeyed();
      $tid = array_key_first($results);

      // set the cache
      \Drupal::cache()
        ->set(
          self::articleTypesCacheId,
          $tid,
          Cache::PERMANENT,
          ['taxonomy_term:' . $tid]
      );
    }

    return $tid;
  }

  /**
   * @param Datetime $date
   */
  public static function getEoDayDatetime(\Datetime $date) {
    $date->modify('tomorrow');
    $date_timestamp = $date->getTimestamp();
    $date->setTimestamp($date_timestamp - 1);
  }

}
