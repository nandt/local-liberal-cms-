<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Resources;

use Drupal\unicorn_core\Support\Paginator;

final class PaginatorResource {

  /**
   * @return array{
   *   total: int,
   *   count: int,
   *   items_per_page: int,
   *   current_page: int,
   *   total_pages: int,
   *   has_next_page: bool,
   *   has_previous_page: bool,
   *   previous_page: ?int,
   *   next_page: ?int,
   *   last_page: int
   * }
   */
  public function toApi(Paginator $paginator): array {
    return [
      'total' => $paginator->getTotal(),
      'count' => $paginator->getCount(),
      'items_per_page' => $paginator->getItemsPerPage(),
      'current_page' => $paginator->getCurrentPage(),
      'total_pages' => $paginator->getTotalPages(),
      'has_next_page' => $paginator->hasNextPage(),
      'has_previous_page' => $paginator->hasPreviousPage(),
      'previous_page' => $paginator->getPreviousPage(),
      'next_page' => $paginator->getNextPage(),
      'last_page' => $paginator->getLastPage(),
    ];
  }

}
