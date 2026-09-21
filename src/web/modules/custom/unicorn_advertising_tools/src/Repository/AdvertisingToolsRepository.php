<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Repository;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Statement\FetchAs;
use LogicException;
use RuntimeException;

/**
 * Builds and runs a unicorn_advertising_tools select query.
 *
 * Safe to inject as a shared service: every terminal method (paginate()/
 * first()) resets the internal query back to a fresh, virgin state before
 * returning, so the next caller always starts clean.
 */
final class AdvertisingToolsRepository {

  private SelectInterface $query;

  public function __construct(
    private readonly Connection $connection,
  ) {
    $this->query = $this->newQuery();
  }

  public function withNode(): self {
    $this->query->fields('n', ['title']);
    $this->query->innerJoin('node_field_data', 'n', 'n.nid = lat.entity_id');

    return $this;
  }

  public function whereEntityId(int $entityId): self {
    $this->query->condition('lat.entity_id', $entityId);

    return $this;
  }

  public function whereState(bool $state): self {
    $this->query->condition('lat.state', (int) $state);

    return $this;
  }

  public function whereTitle(string $title): self {
    if (!array_key_exists('n', $this->query->getTables())) {
      throw new LogicException('Node data required.');
    }

    $this->query->condition('n.title', '%' . $this->connection->escapeLike($title) . '%', 'LIKE');

    return $this;
  }

  public function orderByHistoryDate(string $direction = 'DESC'): self {
    $this->query->orderBy('lat.history_date', $direction);

    return $this;
  }

  public function orderByPromotedDate(string $direction = 'DESC'): self {
    $this->query->orderBy('lat.promoted_date', $direction);

    return $this;
  }

  public function orderById(string $direction = 'DESC'): self {
    $this->query->orderBy('lat.entity_id', $direction);

    return $this;
  }

  /**
   * @todo: move to base class
   * @return array{data: array<int, array<string, mixed>>, total: int}
   */
  public function paginate(int $page, int $perPage): array {
    $countQuery = clone $this->query;
    $total = (int) $countQuery->countQuery()->execute()?->fetchField();

    $this->query->range($page * $perPage, $perPage);

    $statement = $this->query->execute();
    $this->resetQuery();

    if (is_null($statement)) {
      throw new RuntimeException('Query failed to execute.');
    }

    return [
      'data' => $statement->fetchAll(FetchAs::Associative),
      'total' => $total,
    ];
  }

  /**
   * @todo: move to base class
   * @return array<string, mixed>|null
   */
  public function first(): ?array {
    $record = $this->query->execute()?->fetchAssoc() ?: null;
    $this->resetQuery();

    return $record;
  }

  /**
   * @todo: move to base class
   * @return array<int, array<string, mixed>>
   */
  public function all(): array {
    $statement = $this->query->execute();
    $this->resetQuery();

    if (is_null($statement)) {
      throw new RuntimeException('Query failed to execute.');
    }

    return $statement->fetchAll(FetchAs::Associative);
  }

  /**
   * @todo: move to base class
   */
  private function resetQuery(): void {
    $this->query = $this->newQuery();
  }

  /**
   * @todo: would be abstract function in class
   */
  private function newQuery(): SelectInterface {
    return $this->connection->select('unicorn_advertising_tools', 'lat')
      ->fields('lat');
  }

}
