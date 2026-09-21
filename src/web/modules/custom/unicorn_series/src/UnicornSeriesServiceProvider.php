<?php

declare(strict_types=1);

namespace Drupal\unicorn_series;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_series\Hook\SeriesHooks;
use Drupal\unicorn_series\Plugin\Validation\Constraint\UniqueSeriesNameConstraintValidator;
use Drupal\unicorn_series\Service\SeriesUsageChecker;

final class UnicornSeriesServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      SeriesUsageChecker::class,
      SeriesHooks::class,
      UniqueSeriesNameConstraintValidator::class,
    ];
  }

}
