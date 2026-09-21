<?php

declare(strict_types=1);

namespace Drupal\unicorn_opinions\Resources;

use Drupal\unicorn_core\Resources\BaseResource;

class AdminOpinionsResource extends BaseResource
{
  /**
   * @param list<int> $data
   *
   * @return array{ids: list<int|null>}
   */
  public function toDashboardApi(array $data): array
  {
    return [
      'ids' => $data,
    ];
  }
}
