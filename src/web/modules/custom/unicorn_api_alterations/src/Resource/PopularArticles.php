<?php

namespace Drupal\unicorn_api_alterations\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exposes the six most popular article pages from the current statistics day.
 */
final class PopularArticles extends EntityQueryResourceBase implements ContainerInjectionInterface {

  private const int ARTICLE_TYPE_TID = 41;

  private const int LIMIT = 6;

  public function __construct(
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): self {
    $database = $container->get('database');
    assert($database instanceof Connection);
    return new self($database);
  }

  public function process(Request $request): ResourceResponse {
    $cacheability = new CacheableMetadata()
      ->addCacheTags(['node_list:article_liberal'])
      // Statistics counters are updated independently of node cache tags.
      ->setCacheMaxAge(300);

    $select = $this->database->select('node_field_data', 'n');
    $select->addField('n', 'nid');
    $select->join('node_counter', 'nc', 'nc.nid = n.nid');
    $select->join('taxonomy_index', 'ti', 'ti.nid = n.nid');
    $statement = $select
      ->condition('n.type', 'article_liberal')
      ->condition('n.status', NodeInterface::PUBLISHED)
      ->condition('n.default_langcode', 1)
      ->condition('ti.tid', self::ARTICLE_TYPE_TID)
      ->orderBy('nc.daycount', 'DESC')
      ->range(0, self::LIMIT)
      ->execute();
    $nids = $statement?->fetchCol() ?? [];

    $data = new ResourceObjectData([]);

    if ($nids !== []) {
      $query = $this->getEntityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'article_liberal')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('nid', $nids, 'IN');
      $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);

      /** @var array<int, ResourceObject> $objects_by_nid */
      $objects_by_nid = [];
      foreach ($data->getIterator() as $object) {
        assert($object instanceof ResourceObject);
        $objects_by_nid[$object->getField('drupal_internal__nid')->value ?? 0] = $object;
      }

      /** @var list<ResourceObject> $ordered */
      $ordered = [];
      foreach ($nids as $nid) {
        if (isset($objects_by_nid[$nid])) {
          $ordered[] = $objects_by_nid[$nid];
        }
      }
      $data = new ResourceObjectData($ordered);
    }

    $response = $this->createJsonapiResponse($data, $request, 200, [], NULL);
    if ($response instanceof CacheableResponseInterface) {
      $response->addCacheableDependency($cacheability);
    }
    return $response;
  }

}
