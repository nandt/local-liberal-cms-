<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields;

use Drupal\unicorn_computed_fields\EventSubscriber\ArticleStatusFilterSubscriber;
use Drupal\unicorn_computed_fields\Filter\ArticleStatusFilter;
use Drupal\unicorn_computed_fields\Service\DeletionEligibility;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;

/**
 * Registers autowired services provided by the module.
 */
final class UnicornComputedFieldsServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      ArticleStatusFilter::class,
      ArticleStatusFilterSubscriber::class,
      DeletionEligibility::class,
    ];
  }

}
