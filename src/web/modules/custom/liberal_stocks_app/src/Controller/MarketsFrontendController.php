<?php

namespace Drupal\liberal_stocks_app\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\liberal_stocks_app\Resources\StocksAppResource;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\unicorn_core\Accessors\Data;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\node\Entity\Node;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Cache;
use Drupal\liberal_stocks_app\Utils\LiberalStocksAppUtils;
use RuntimeException;

/**
 * @phpstan-consistent-constructor
 */
class MarketsFrontendController extends ControllerBase {

  private const int ITEMS_PER_PAGE = 12;
  private const int PAGER_LIMIT = 50;

  public function __construct(
    private readonly Connection $database,
    private readonly StocksAppResource $stocksAppResource,
  ) {}

  public function getMarketsArticles(Request $request): CacheableJsonResponse {

    $cache_tags = [
      'liberalmetohes'
    ];

    // Get query parameters for pagination.
    $page = $request->input('page', 0);
    $items_per_page = $request->input('items_per_page', self::ITEMS_PER_PAGE);
    $scheduler_date = $request->input('scheduler_date');

    // limit the pager to avoid setting it too high
    $items_per_page = ($items_per_page > self::PAGER_LIMIT) ? self::PAGER_LIMIT : $items_per_page;

    $offset = $page * $items_per_page;

    // normal query to get data
    $query = $this->database->select('node_field_data', 'nfd');

    // when using multiple joins you need to write them line by line because join returns the alias
    $query->innerJoin('node__field_metohi', 'nfm', 'nfd.nid = nfm.entity_id');

    $query
    ->fields('nfd', ['nid', 'title'])
    ->condition('nfd.status', 1)
    ->condition('nfd.type', "article_liberal")
    ->orderBy('nfd.created','DESC')
    ->groupBy('nfd.nid')
    ->range($offset, $items_per_page);

    if(!empty($scheduler_date)) {
      // Format the date appropriately
      $scheduler_formatted = LiberalStocksAppUtils::getFormattedQueryDate($scheduler_date);
      $query->condition("nfd.created", $scheduler_formatted, ">=");
    }

    // if no date was given, get more performant count query
    if(empty($scheduler_date)) {
      $count_query = $this->getCountQuery($this->database);
      $count_results = $count_query->countQuery()->execute();
    }
    else {
      // just use our query but make it a count query
      $count_query = clone $query;
      $count_query = $count_query->range();
      $count_results = $count_query->countQuery()->execute();
    }

    if(is_null($count_results)) {
      throw new RuntimeException('Something went wrong.');
    }

    $count_results = $count_results->fetchField();

    // total pages. Divide and round up
    $total_pages = ceil($count_results / $items_per_page);
    $pager_info = LiberalStocksAppUtils::getPagerInfo($page, $items_per_page, $count_results, (int) $total_pages);

    $nids = $query->execute();
    if(is_null($nids)) {
      throw new RuntimeException('Something went wrong.');
    }

    // Load nodes.
    $nodes = $nids->fetchAll(\Drupal\Core\Database\Statement\FetchAs::Associative);
    $nodes = array_column($nodes, 'nid');
    $nodes = Node::loadMultiple($nodes);

    // Convert nodes to JSON.
    $node_data = [];
    $terms = $this->getTermsData($nodes);

    foreach ($nodes as $node) {
      $node_data['data'][] = $this->stocksAppResource->toFrontendCall($node, $terms);
    }

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

  private function getCountQuery(Connection $connection): SelectInterface {
    // count query mandatory because the application needs it.
    $count_query = $connection->select('node_field_data', 'nfd');
    $count_query->innerJoin('node__field_metohi', 'nfm', 'nfd.nid = nfm.entity_id');
    $count_query
    ->fields('nfd', ['nid'])
    ->condition('nfd.status', 1)
    ->condition('nfd.type', "article_liberal")
    ->groupBy('nfd.nid');

    return $count_query;
  }

  /**
   * @param array<NodeInterface> $nodes
   *
   * @return array<TermInterface>
   */
  public function getTermsData(array $nodes): array
  {
    $terms = [];

    foreach ($nodes as $node) {
      $author = $node->get('field_arthrografos')->getValue();
      $author_id = Data::get($author, '0.target_id');

      if (isset($author_id)) {
        $terms[] = $author_id;
      }

      $category = $node->get('field_liberal_category')->getValue();
      $category_id = Data::get($category, '0.target_id');

      if (isset($category_id)) {
        $terms[] = $category_id;
      }
    }

    // load everything in 1 go
    return Term::loadMultiple($terms);
  }
}
