<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools;

use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Repository\AdvertisingToolsRepository;
use Drupal\unicorn_advertising_tools\Resource\ReadMoreArticles;
use Drupal\unicorn_advertising_tools\Resource\AdvertisingToolsResource;
use Drupal\unicorn_advertising_tools\Services\AdTracker;
use Drupal\unicorn_advertising_tools\Services\WeightedRandomSelector;
use Drupal\unicorn_advertising_tools\Support\AdvertisingToolsUtils;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;

class UnicornAdvertisingToolsServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
    // @phpstan-ignore-next-line
    return [
      AdTracker::class,
      AdvertisingToolsUtils::class,
      AdvertisingToolsConfig::class,
      AdvertisingToolsRepository::class,
      AdvertisingToolsResource::class,
      WeightedRandomSelector::class,
      ReadMoreArticles::class,
    ];
  }

}
