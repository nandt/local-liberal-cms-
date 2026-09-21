<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Repository;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_search\Configuration\SearchConfig;
use Drupal\unicorn_search\Dto\Filters\SearchFilters;
use RuntimeException;

final class SearchRepository {

  private QueryInterface $query;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SearchConfig $searchConfig,
  ) {
    $this->query = $this->newQuery();
  }

  /**
   * @return Collection<string, mixed>
   */
  public function search(SearchFilters $filters): Collection {

    $this->query->keys($filters->query);
    $this->query->setFulltextFields(['auto_aggregated_fulltext_field']);

    $this->orderByDateCreated($filters->direction);

    $this->query->range($filters->page * $filters->perPage, $filters->perPage);

    $results = $this->query->execute();
    $this->resetQuery();

    $items = Collection::wrap(array_values($results->getResultItems()));

    return Collection::wrap([
      'items' => $items->toArray(),
      'total' => $results->getResultCount(),
    ]);
  }

  private function orderByDateCreated(string $direction = 'DESC'): void {
      $this->query->sort('created', $direction);
  }

  private function newQuery(): QueryInterface {
    $searchIndex = $this->entityTypeManager->getStorage('search_api_index')->load($this->searchConfig->getIndexId());

    if (!$searchIndex instanceof IndexInterface) {
      throw new RuntimeException('Search index not found.');
    }

    return $searchIndex->query();
  }

  private function resetQuery(): void {
    $this->query = $this->newQuery();
  }

}
