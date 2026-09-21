<?php

namespace Drupal\liberal_stocks_app\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\liberal_stocks_app\Resources\StocksAppResource;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Cache;
use Drupal\liberal_stocks_app\Utils\LiberalStocksAppUtils;
use RuntimeException;

/**
 * @phpstan-consistent-constructor
 */
class MarketsBackendController extends ControllerBase {

  const ITEMS_PER_PAGE = 12;
  const PAGER_LIMIT = 50;

  public function __construct(
    private readonly Connection $database,
    private readonly StocksAppResource $stocksAppResource,
  ) {}

  public function getMarketsArticles(Request $request): CacheableJsonResponse
  {

    // Get query parameters for pagination.
    $page = $request->input('page', 0);
    $items_per_page = $request->input('items_per_page', self::ITEMS_PER_PAGE);

    // limit the pager to avoid setting it too high
    $items_per_page = ($items_per_page > self::PAGER_LIMIT) ? self::PAGER_LIMIT : $items_per_page;

    $scheduler_date = $request->input('scheduler_date');
    $offset = $page * $items_per_page;


    $query = $this->database->select('node_field_data', 'nfd');

    // when using multiple joins you need to write them line by line because join returns the alias
    $query->innerJoin('node__field_metohi', 'nfm', 'nfd.nid = nfm.entity_id');
    $query->innerjoin('taxonomy_term__field_stc_symbol_en', 'symbol', 'nfm.field_metohi_target_id = symbol.entity_id');
    $query->leftjoin('node__field_liberal_category', 'nflc', 'nfd.nid = nflc.entity_id');
    $query->innerJoin('taxonomy_term_field_data','ttfd','nflc.field_liberal_category_target_id = ttfd.tid');
    $query->leftjoin('node__field_teleytaia_enimerosi', 'nfte', 'nfd.nid = nfte.entity_id');

    $query
    ->fields('nfd', ['nid', 'title', 'created'])
    ->fields('nfte', ['field_teleytaia_enimerosi_value'])
    ->fields('nflc', ['field_liberal_category_target_id'])
    ->fields('symbol', ['field_stc_symbol_en_value'])
    ->fields('ttfd', ['name'])
    ->condition('nfd.status', 1)
    ->condition('nfd.type', "article_liberal")
    ->orderBy('nfd.created','DESC')
    ->range($offset, $items_per_page);

    if(!empty($scheduler_date)) {
      // Format the date appropriately
      $scheduler_formatted = LiberalStocksAppUtils::getFormattedQueryDate($scheduler_date);
      $query->condition("nfd.created", $scheduler_formatted, ">=");
    }

    $executed = $query->execute();

    if (is_null($executed)) {
      throw new RuntimeException('Something went wrong.');
    }

    $nodes = $executed->fetchAll();

    // Convert nodes to JSON.
    $node_data['data'] = [];

    foreach ($nodes as $node) {
      $node_data['data'][] = $this->stocksAppResource->toBackendCall($node);
    }

    $cache_tags = [
      'liberalmetohes'
    ];

    // we dont have count_results and total_pages in this Controller
    $pager_info = liberalStocksAppUtils::getPagerInfo($page, $items_per_page);
    $node_data['pager'] = $pager_info;

    // Add Cache settings for Max-age and URL context, tags
      $node_data['#cache'] = [
        'max-age' => Cache::PERMANENT,
        'tags' => $cache_tags,
        'contexts' => [
          'url',
        ]
    ];

    $response = ResponseBuilder::make($node_data)->cacheableJson();
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($node_data));

    return $response;
  }
}
