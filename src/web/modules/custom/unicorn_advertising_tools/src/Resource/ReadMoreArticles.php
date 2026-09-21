<?php

namespace Drupal\unicorn_advertising_tools\Resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Drupal\unicorn_core\Support\Collection;
use Symfony\Component\HttpFoundation\Request;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Support\AdvertisingToolsUtils;
use Symfony\Component\HttpFoundation\Response;

final class ReadMoreArticles extends EntityQueryResourceBase {

  public function __construct(
    private readonly AdvertisingToolsUtils $advertisingToolsUtils,
    private readonly AdvertisingToolsConfig $advertisingToolsConfig,
  ) {}

  /**
   * Process the resource request.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function process(Request $request, NodeInterface $node): ResourceResponse {

    $readMoreArticles = $node->get('field_homepage_bullets')->getValue();

    if(is_array($readMoreArticles)) {
      $readMoreArticles = Collection::wrap($readMoreArticles)->pluck('target_id');
    }

    $cacheMetadata = $this->getCacheMetadata($node);
    $promotedArticles = $this->advertisingToolsUtils->selectNodes($readMoreArticles, (int) $node->id());
    $this->insertPromotedArticles($readMoreArticles, $promotedArticles);

    $data = new ResourceObjectData([]);
    $pagination_links = NULL;
    if($readMoreArticles->isNotEmpty()) {
      $query = $this->getEntitiesQuery($readMoreArticles);
      $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheMetadata);
      $data = $this->reOrderItems($data, $readMoreArticles);
    }

    $response = $this->createJsonapiResponse(
      $data,
      $request,
      Response::HTTP_OK,
      [],
      $pagination_links,
      ['promoted' => $promotedArticles->toArray()]);

    if($response instanceof CacheableResponseInterface) {
      $response->addCacheableDependency($cacheMetadata);
    }

    return $response;
  }

  private function getCacheMetadata(NodeInterface $node): CacheableMetadata
  {
    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheContexts(['url.path']);
    $cacheMetadata->addCacheTags([$this->advertisingToolsConfig->getPromotedListTag()]);
    $cacheMetadata->addCacheableDependency($node);

    return $cacheMetadata;
  }

  /**
   * @param Collection<int, int|string> $readMoreArticles
   */
  private function reOrderItems(ResourceObjectData $data, Collection $readMoreArticles): ResourceObjectData {
    /** @var Collection<int, ResourceObject> $resources */
    $resources = Collection::wrap($data);

    $resourceMap = $resources->keyBy(
      fn(ResourceObject $resourceObject): int => (int) $resourceObject->getField('drupal_internal__nid')->value,
    );

    $orderedObjects = $readMoreArticles
      ->transform(static fn($nid) => $resourceMap[$nid] ?? null)
      ->filter(static fn($item): bool => !is_null($item));

    return new ResourceObjectData($orderedObjects->toArray());
  }

  /**
   * @param Collection<int, int|string> $readMoreArticles
   */
  private function getEntitiesQuery(Collection $readMoreArticles): QueryInterface {
    return $this->getEntityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('nid', $readMoreArticles->toArray(), 'IN');
  }

  /**
   * @param Collection<int, int|string> $readMoreArticles
   * @param Collection<int, int> $promotedArticles
   */
  private function insertPromotedArticles(Collection $readMoreArticles, Collection $promotedArticles): void {

    if($promotedArticles->isEmpty()) {
      return;
    }

    if($readMoreArticles->isEmpty()) {
      $nextItem = $promotedArticles->first();

      if ($nextItem) {
        $readMoreArticles->add($nextItem);
      }

      return;
    }

    $processPromotedArticles = Collection::wrap($promotedArticles->toArray());
    $this->advertisingToolsConfig->getPromotedArticlePositions()->each(function($position) use ($readMoreArticles, $processPromotedArticles): void {
      if ($processPromotedArticles->isNotEmpty()) {
        $promotedArticle = $processPromotedArticles->first();

        $readMoreArticles->splice($position, 0, $promotedArticle);
        $processPromotedArticles->removeElement($promotedArticle);
      }
    });
  }

}
