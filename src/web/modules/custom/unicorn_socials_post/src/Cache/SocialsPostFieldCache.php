<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Cache;

use Drupal\Core\Database\Connection;
use Drupal\unicorn_core\Support\Collection;

/**
 * Request-scoped static cache for social sharing rows.
 * so that computed fields on nodes never cause N+1 queries.
 */
class SocialsPostFieldCache {

  /**
   * @var ?Collection<int, array<string, mixed>|null>
   */
  private static ?Collection $cache = null;

  /**
   * @return Collection<int, array<string, mixed>|null>
   */
  private static function getCache(): Collection {
    return self::$cache ??= Collection::wrap([]);
  }

  /**
   * @param list<int> $nodeIds
   */
  public static function preload(array $nodeIds, Connection $connection): void {
    $nodeCollection = Collection::wrap($nodeIds);

    $missingNodes = $nodeCollection->filter(
      static fn(int $nodeId): bool => !self::getCache()->containsKey($nodeId),
    );

    if ($missingNodes->isEmpty()) {
      return;
    }

    // Pre-mark all as null so nodes with no sharing record don't repeat queries.
    $missingNodes->each(static function (int $nodeId): void {
      self::getCache()->set($nodeId, null);
    });

    /** @var list<int> $missingNodeIds */
    $missingNodeIds = array_values($missingNodes->toArray());

    $newNodes = Collection::wrap(
      self::queryData($missingNodeIds, $connection),
    );

    $newNodes->each(static function (array $item): void {
      self::getCache()->set((int) $item['entity_id'], $item);
    });
  }

  /**
   * @return array<string, mixed>|null
   */
  public static function get(int $nodeId): ?array {
    return self::getCache()[$nodeId] ?? null;
  }

  /**
   * @param list<int> $entityIds
   *
   * @return array<array<string, mixed>>
   */
  private static function queryData(array $entityIds, Connection $connection): array {
    return $connection->select('unicorn_social_sharing', 'uss')
      ->fields('uss')
      ->condition('entity_id', $entityIds, 'IN')
      ->execute()
      ?->fetchAllAssoc('entity_id', \PDO::FETCH_ASSOC) ?? [];
  }

}
