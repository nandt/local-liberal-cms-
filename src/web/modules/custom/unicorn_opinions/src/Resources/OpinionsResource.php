<?php

declare(strict_types=1);

namespace Drupal\unicorn_opinions\Resources;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes a request for the read more articles for the requested node.
 */

class OpinionsResource extends EntityQueryResourceBase
{
    private readonly ImmutableConfig $config;

    public function __construct(
        private readonly ConfigFactoryInterface $configFactory,
    ) {
        $this->config = $this->configFactory->get('unicorn_opinions.settings');
    }

  /**
   * Process the resource request.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
    public function process(Request $request): ResourceResponse
    {
        $taxonomyIds = $this->config->get('opinions');
        $nodeIds = $this->getOpinions($taxonomyIds);

        $loadedNodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nodeIds);
        $cacheability = $this->getCacheMetadata($loadedNodes);

        $data = $this->createCollectionDataFromEntities($loadedNodes, false);
        $response = $this->createJsonapiResponse($data, $request);

        if ($response instanceof CacheableResponseInterface) {
            $response->addCacheableDependency($cacheability);
        }

        return $response;
    }

  /**
   * @param array<int> $taxonomyIds
   *
   * @return array<int>
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
    private function getOpinions(array $taxonomyIds): array
    {

        $items = [];
        foreach ($taxonomyIds as $position => $tid) {
            $result = $this->getEntityQuery('node')
            ->accessCheck(false)
            ->condition('status', NodeInterface::PUBLISHED)
            ->condition('field_liberal_category.target_id', $tid)
            ->sort('created', 'DESC')
            ->range(0, 1)
            ->execute();

            if (is_array($result) && !empty($result)) {
                $items[$position] = reset($result);
            }
        }

        return $items;
    }

  /**
   * @param array<int, EntityInterface> $entities
   */
    private function getCacheMetadata(array $entities = []): CacheableMetadata
    {
        $cacheMetadata = new CacheableMetadata();

        $cacheMetadata->addCacheableDependency($this->config);
        foreach ($entities as $entity) {
            $cacheMetadata->addCacheableDependency($entity);
        }

        return $cacheMetadata;
    }
}
