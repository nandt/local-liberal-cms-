<?php

declare(strict_types=1);

namespace Drupal\unicorn_refresh_on_viewable;

use Drupal\unicorn_core\UnicornCoreServiceProvider;
use Drupal\unicorn_refresh_on_viewable\Controller\UnicornRefreshOnViewableController;

final class UnicornRefreshOnViewableServiceProvider extends UnicornCoreServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      UnicornRefreshOnViewableController::class,
    ];
  }

}
