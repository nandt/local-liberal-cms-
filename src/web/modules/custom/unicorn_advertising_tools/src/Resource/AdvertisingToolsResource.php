<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Resource;

use Drupal\unicorn_core\Accessors\Data;
use Drupal\unicorn_core\Resources\BaseResource;
use Drupal\unicorn_core\Support\Collection;

/**
 * @phpstan-type AdvertisingToolApiRecord array{
 *   entity_id: int,
 *   title: string,
 *   impressions: int,
 *   target_impressions: int,
 *   clicks: int,
 *   ctr: string,
 *   priority: int,
 *   state: bool,
 *   start_date: ?string,
 *   end_date: ?string,
 *   promoted_date: ?string,
 *   history_date: ?string
 * }
 */
final class AdvertisingToolsResource extends BaseResource {

  /**
   * @param array<string, mixed> $data
   *
   * @return AdvertisingToolApiRecord
   */
  public function toApi(array $data): array {
    $ctr = $this->calculateCtr($data);

    return [
      'entity_id' => (int) Data::get($data, 'entity_id', 0),
      'title' => (string) Data::get($data, 'title', ''),
      'impressions' => (int) Data::get($data, 'impressions', 0),
      'target_impressions' => (int) Data::get($data, 'target_impressions', 0),
      'clicks' => (int) Data::get($data, 'clicks', 0),
      'ctr' => $ctr,
      'priority' => (int) Data::get($data, 'priority', 0),
      'state' => (bool) Data::get($data, 'state', 0),
      'start_date' => $this->getTimestampAsHtmlDatetime((int) Data::get($data, 'start_date', 0)),
      'end_date' => $this->getTimestampAsHtmlDatetime((int) Data::get($data, 'end_date', 0)),
      'promoted_date' => $this->getTimestampAsHtmlDatetime((int) Data::get($data, 'promoted_date', 0)),
      'history_date' => $this->getTimestampAsHtmlDatetime((int) Data::get($data, 'history_date', 0)),
    ];
  }

  /**
   * @param array<int, array<string, mixed>> $rows
   *
   * @return array<int, AdvertisingToolApiRecord>
   */
  public function toApiCollection(array $rows): array {
    return Collection::wrap($rows)
      ->transform(fn(array $row): array => $this->toApi($row), Collection::class)
      ->toArray();
  }

  /**
   * @param array<string, mixed> $data
   */
  private function calculateCtr(array $data): string {
    $impressions = (int) Data::get($data, 'impressions', 0);
    $clicks = (int) Data::get($data, 'clicks', 0);

    $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

    return "{$ctr}%";
  }

}
