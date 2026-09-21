<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Resources;

use Drupal\unicorn_core\Accessors\Data;
use Drupal\unicorn_core\Resources\BaseResource;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_socials_post\Services\SocialTasks;

class SocialsPostResource extends BaseResource
{

  /**
   * @param array<string, mixed> $data
   *
   * @return array{
   *   entity_id: int,
   *   count: int,
   *   last_updated_at: ?string
   * }
   */
  public function toDashboardApiSingle(array $data, string $platform): array
  {
    return [
      'entity_id' => (int) Data::get($data, 'entity_id', 0),
      'count' => (int) Data::get($data, SocialTasks::SHARE[$platform][0], 0),
      'last_updated_at' => $this->getTimestampAsHtmlDatetime((int) Data::get($data, SocialTasks::SHARE[$platform][1], 0)),
    ];
  }

  /**
   * @param array<string, mixed> $data
   *
   * @return array<string, array{
   *   count: int,
   *   last_updated_at: ?string
   * }>
   */
  public function toAllPlatformsSingle(array $data): array
  {
    return Collection::wrap(array_keys(SocialTasks::SHARE))
      ->mapWithKeys(fn(string $platform): array => [$platform => $this->toAllPlatformsSingleRow($data, $platform)])->toArray();
  }

  /**
   * @param array<string, mixed> $data
   *
   * @return array<int, array<string, array{
   *   count: int,
   *   last_updated_at: ?string
   * }>>
   */
  public function toAllPlatforms(array $data): array {
    return Collection::wrap($data)
      ->transform(fn(array $item): array => $this->toAllPlatformsSingle($item), Collection::class)
      ->toArray();
  }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *   count: int,
     *   last_updated_at: ?string
     * }
     */
    private function toAllPlatformsSingleRow(array $data, string $platform): array
    {
        return [
            'count' => (int) Data::get($data, SocialTasks::SHARE[$platform][0], 0),
            'last_updated_at' => $this->getTimestampAsHtmlDatetime((int) Data::get($data, SocialTasks::SHARE[$platform][1], 0)),
        ];
    }
}
