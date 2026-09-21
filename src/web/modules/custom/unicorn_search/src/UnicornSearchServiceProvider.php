<?php

declare(strict_types=1);

namespace Drupal\unicorn_search;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_search\Configuration\SearchConfig;
use Drupal\unicorn_search\Repository\SearchRepository;
use Drupal\unicorn_search\Resource\SearchResource;
use Drupal\unicorn_search\Support\ItemNodeIdResolver;

class UnicornSearchServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      SearchConfig::class,
      SearchRepository::class,
      SearchResource::class,
      ItemNodeIdResolver::class,
    ];
  }

}
