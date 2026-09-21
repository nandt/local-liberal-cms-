<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations;

use Drupal\unicorn_api_alterations\Plugin\Validation\Constraint\UniqueFeatureNameConstraintValidator;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;

final class UnicornApiAlterationsServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      UniqueFeatureNameConstraintValidator::class,
    ];
  }

}
