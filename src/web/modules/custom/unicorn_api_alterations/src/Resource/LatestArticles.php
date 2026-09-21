<?php

namespace Drupal\unicorn_api_alterations\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class LatestArticles extends EntityQueryResourceBase {

  public function process(Request $request): ResourceResponse {
    $page = $request->query->all('page');
    $limit = (int) ($page['limit'] ?? 10);
    $limit = max(1, min($limit, 50));

    $cacheability = new CacheableMetadata()
      ->addCacheContexts(['url.query_args:page'])
      ->addCacheTags(['node_list:article_liberal']);

    $query = $this->getEntityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);

    $response = $this->createJsonapiResponse($data, $request, 200);
    $response->addCacheableDependency($cacheability);

    return $response;
  }
}
