<?php

namespace Drupal\liberal_node_loader\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class PreloadNidsController extends ControllerBase {

  /**
   * @var int
   */

  const DEFAULT_ARTICLE_LIST_TTL = 180;

  /**
   * @param int $section
   * @param Request $request
   * @return CacheableJsonResponse
*/

  public function preloadNids($section, Request $request): CacheableJsonResponse {

    // get data from ajax
    $loading_nids = [];
    $page_data = json_decode($request->getContent());
    !empty($page_data->current_nid) ? $current_nid = $page_data->current_nid : $current_nid = 0;

    // preload next section
    $response = '';

    $loading_nids = \Drupal::service('liberal_node_loader.get_loading_nids')->getLoadingNids($current_nid);

    $response_data = [
      'toLoadNids' => $loading_nids['with_current_nid'],
    ];

    $response = new CacheableJsonResponse($response_data);

    // get config for TTL
    $config = \Drupal::config('liberal_async_blocks.admin_settings');
    $edge_cache_ttl = (int) $config->get('node_loader_ttl')['node_loader_list_ttl'] ?? self::DEFAULT_ARTICLE_LIST_TTL;

    // disable Drupal Caching
    $response->getCacheableMetadata()->setCacheMaxAge(0);

    // Set cache headers
    $response->setSharedMaxAge($edge_cache_ttl);
    $response->setMaxAge($edge_cache_ttl);
    $response->setPublic();

    return $response;
  }

}
