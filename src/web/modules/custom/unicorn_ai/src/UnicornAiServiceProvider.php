<?php

declare(strict_types=1);

namespace Drupal\unicorn_ai;

use Drupal\unicorn_ai\Configuration\AiConfig;
use Drupal\unicorn_core\UnicornCoreServiceProvider;

final class UnicornAiServiceProvider extends UnicornCoreServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      AiConfig::class,
    ];
  }

}
