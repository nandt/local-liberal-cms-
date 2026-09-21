<?php

declare(strict_types=1);

namespace Drupal\liberal_stocks_app;

use Drupal\liberal_stocks_app\Configuration\NotificationsConfig;
use Drupal\liberal_stocks_app\Resources\StocksAppResource;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;

class LiberalStocksAppServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
    // @phpstan-ignore-next-line
    return [
      StocksAppResource::class,
      NotificationsConfig::class,
    ];
  }
}
