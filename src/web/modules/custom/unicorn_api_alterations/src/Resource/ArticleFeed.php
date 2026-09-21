<?php

namespace Drupal\unicorn_api_alterations\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

final class ArticleFeed extends EntityQueryResourceBase {

  private const array FILTERS = [
    'category' => 'field_liberal_category',
    'tag'      => 'field_liberal_tags',
    'author'   => 'field_arthrografos',
  ];

  public function process(Request $request): ResourceResponse {
    $q = $request->query;
    $limit = max(1, min((int) ($q->all('page')['limit'] ?? 20), 50));
    $sort = $q->get('sort') === 'created' ? 'created' : 'changed';
    $filter = (string) $q->get('filterBy');
    $id = (string) $q->get('termUuid');

    $cacheability = new CacheableMetadata()
      ->addCacheContexts(['url.query_args:page', 'url.query_args:sort', 'url.query_args:filterBy', 'url.query_args:termUuid'])
      ->addCacheTags(['node_list:article_liberal']);

    $db = \Drupal::database();
    $select = $db->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'article_liberal')
      ->condition('n.status', NodeInterface::PUBLISHED)
      ->condition('n.default_langcode', 1);

    if (isset(self::FILTERS[$filter]) && $id !== '') {
      $tid = $db->select('taxonomy_term_data', 't')
        ->fields('t', ['tid'])
        ->condition('t.uuid', $id)
        ->execute()
        ->fetchField();
      if (!$tid) {
        $empty = $this->createJsonapiResponse(new ResourceObjectData([]), $request, 200, [], NULL);
        $empty->addCacheableDependency($cacheability);
        return $empty;
      }
      $field = self::FILTERS[$filter];
      $select->join('node__' . $field, 'f', 'f.entity_id = n.nid AND f.deleted = 0');
      $select->condition('f.' . $field . '_target_id', $tid);
    }

    $nids = $select->orderBy('n.' . $sort, 'DESC')->range(0, $limit)->execute()->fetchCol();

    if (empty($nids)) {
      $empty = $this->createJsonapiResponse(new ResourceObjectData([]), $request, 200, [], NULL);
      $empty->addCacheableDependency($cacheability);
      return $empty;
    }

    $query = $this->getEntityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('nid', $nids, 'IN');
    $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);

    if ($data instanceof ResourceObjectData) {
      $map = [];
      foreach ($data->getIterator() as $obj) {
        $map[$obj->getField('drupal_internal__nid')->value ?? 0] = $obj;
      }
      $ordered = array_values(array_filter(array_map(fn($nid): mixed => $map[$nid] ?? NULL, $nids)));
      $data = new ResourceObjectData($ordered);
    }

    $response = $this->createJsonapiResponse($data, $request, 200, [], NULL);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

}
