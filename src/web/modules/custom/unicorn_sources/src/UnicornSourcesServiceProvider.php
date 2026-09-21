<?php

declare(strict_types=1);

namespace Drupal\unicorn_sources;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_sources\Hook\SourcesHooks;
use Drupal\unicorn_sources\Plugin\Validation\Constraint\UniqueSourceNameConstraintValidator;
use Drupal\unicorn_sources\Service\SourceUsageChecker;

final class UnicornSourcesServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      SourceUsageChecker::class,
      SourcesHooks::class,
      UniqueSourceNameConstraintValidator::class,
    ];
  }

}
