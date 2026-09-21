<?php

namespace Drupal\unicorn_advertising_tools\Support;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Repository\AdvertisingToolsRepository;
use Drupal\unicorn_advertising_tools\Services\WeightedRandomSelector;
use Drupal\unicorn_core\Support\Collection;

/**
 * @phpstan-import-type PromotedArticleRow from WeightedRandomSelector
 */
readonly class AdvertisingToolsUtils {

  private CacheBackendInterface $cache;

  public function __construct(
    CacheFactoryInterface $cacheFactory,
    private AdvertisingToolsConfig $advertisingToolsConfig,
    private CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private AdvertisingToolsRepository $advertisingToolsRepository,
    private WeightedRandomSelector $weightedRandomSelector
  ) {
    $this->cache = $cacheFactory->get('default');
  }

  /**
   * $items is an array from get(field)
   *
   * @param Collection<int, int|string> $items
   * @return Collection<int, int>
   */
  public function selectNodes(Collection $items, int $currentNid): Collection
  {
    // exclude current article if it happens to be promoted from the pool
    $promotedArticles = $this->fetchPromotedArticles()
      ->reject(fn ($row, $entityId): bool => (int) $entityId === $currentNid);

    $numberOfPromotedtoSelect = $this->advertisingToolsConfig->getPromotedArticleCount($items->count());
    $selectedArticles = Collection::wrap([]);
    while($numberOfPromotedtoSelect > 0) {
      $selectedArticles->add($this->weightedRandomSelector->select($promotedArticles));
      $numberOfPromotedtoSelect--;
    }

    return $selectedArticles;
  }

  public function clearAdToolsCache(bool $changedState = FALSE): void {
    $tags = match ($changedState) {
      true => [
        $this->advertisingToolsConfig->getPromotedDashboardTag(),
        $this->advertisingToolsConfig->getPromotedListTag(),
      ],
      default => [$this->advertisingToolsConfig->getPromotedListTag()],
    };

    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * @return Collection<int, PromotedArticleRow>
   */
  public function fetchPromotedArticles(): Collection {

    if($cache = $this->cache->get($this->advertisingToolsConfig->getPromotedArticlesCacheId())) {
      return $cache->data;
    }

    $results = $this->queryPromotedArticles();

    /** Always cache to avoid doing queries if there is no promoted article.
     *  We clear the cache tag with advertising tool dashboard actions,
     *  the drush command that enables / disables ads
     */
    $this->cache->set(
      $this->advertisingToolsConfig->getPromotedArticlesCacheId(),
        $results,
        Cache::PERMANENT,
        [$this->advertisingToolsConfig->getPromotedListTag()]
    );

    return $results;
  }

  /**
   * @return Collection<int, PromotedArticleRow>
   */
  public function queryPromotedArticles(): Collection {
    /** @var array<int, PromotedArticleRow> $results */
    $results = $this->advertisingToolsRepository
      ->whereState(true)
      ->all();

    return Collection::wrap($results)->keyBy(fn(array $row): int => (int) $row['entity_id']);
  }
}
