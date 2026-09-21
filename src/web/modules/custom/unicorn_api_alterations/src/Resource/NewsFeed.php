<?php

namespace Drupal\unicorn_api_alterations\Resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

// Date-range filtered article feed. type=block επιστρέφει τα τελευταία 24h
// (για homepage news widget), type=page δίνει paginated όλα τα published
// (για /news-feed listing). Port από mkdn unicorn_api_alterations.
final class NewsFeed extends EntityQueryResourceBase implements ContainerInjectionInterface {

  public function __construct(protected TimeInterface $time)
  {
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('datetime.time'));
  }

  public function process(string $type, Request $request): ResourceResponse {
    $query = match ($type) {
      'block' => $this->getBlockQuery(),
      'page' => $this->getPageQuery(),
      default => $this->getPageQuery(),
    };

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags(['node_list:article_liberal']);
    $cacheability->addCacheContexts(['url.query_args:page']);

    $paginator = $this->getPaginatorForRequest($request);
    $paginator->applyToQuery($query, $cacheability);

    $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);
    $pagination_links = $paginator->getPaginationLinks($query, $cacheability, TRUE);

    $response = $this->createJsonapiResponse($data, $request, 200, [], $pagination_links);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  private function getBlockQuery() {
    $req_time = $this->time->getRequestTime();
    $end = new \DateTime()->setTimezone(new \DateTimeZone('Europe/Athens'))->setTimestamp($req_time);
    $start = (clone $end)->modify('-24 hours');

    return $this->getEntityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('created', $start->getTimestamp(), '>=')
      ->condition('created', $end->getTimestamp(), '<=')
      ->sort('created', 'DESC');
  }

  private function getPageQuery() {
    return $this->getEntityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC');
  }
}
