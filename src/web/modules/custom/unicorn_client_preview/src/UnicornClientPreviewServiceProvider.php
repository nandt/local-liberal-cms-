<?php

declare(strict_types=1);

namespace Drupal\unicorn_client_preview;

use Drupal\unicorn_client_preview\Access\ClientPreviewAccess;
use Drupal\unicorn_core\UnicornCoreServiceProvider;

final class UnicornClientPreviewServiceProvider extends UnicornCoreServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      ClientPreviewAccess::class,
    ];
  }

}
