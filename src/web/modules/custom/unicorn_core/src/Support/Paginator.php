<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Support;

final readonly class Paginator {

  public function __construct(
    private int $total,
    private int $count,
    private int $page,
    private int $perPage,
  ) {
  }

  public function getTotal(): int {
    return $this->total;
  }

  public function getCount(): int {
    return $this->count;
  }

  public function getItemsPerPage(): int {
    return $this->perPage;
  }

  public function getCurrentPage(): int {
    return $this->page;
  }

  public function getTotalPages(): int {
    return $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 0;
  }

  public function hasNextPage(): bool {
    return ($this->page + 1) < $this->getTotalPages();
  }

  public function hasPreviousPage(): bool {
    return $this->page > 0;
  }

  public function getNextPage(): ?int {
    return $this->hasNextPage() ? $this->page + 1 : null;
  }

  public function getPreviousPage(): ?int {
    return $this->hasPreviousPage() ? $this->page - 1 : null;
  }

  public function getLastPage(): int {
    return max(0, $this->getTotalPages() - 1);
  }

}
