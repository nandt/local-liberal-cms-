<?php

declare(strict_types=1);

namespace Drupal\unicorn_promotional_banner;

use Drupal\unicorn_promotional_banner\Configuration\PromotionalBannerConfig;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_promotional_banner\Support\File\FileOperations;

class UnicornPromotionalBannerServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
    // @phpstan-ignore-next-line
    return [
      PromotionalBannerConfig::class,
      FileOperations::class,
    ];
  }

}
