<?php

declare(strict_types=1);

namespace Drupal\unicorn_lazy_load_adtools;

use Drupal\unicorn_core\UnicornCoreServiceProvider;
use Drupal\unicorn_lazy_load_adtools\Controller\UnicornLazyLoadAdToolsController;

class UnicornLazyLoadAdToolsServiceProvider extends UnicornCoreServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      UnicornLazyLoadAdToolsController::class,
    ];
  }

}
