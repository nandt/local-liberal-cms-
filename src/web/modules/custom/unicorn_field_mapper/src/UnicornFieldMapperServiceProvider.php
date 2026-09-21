<?php

declare(strict_types=1);

namespace Drupal\unicorn_field_mapper;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_field_mapper\Configuration\FieldMapConfiguration;
use Drupal\unicorn_field_mapper\Factory\FieldMapFactory;

class UnicornFieldMapperServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
    // @phpstan-ignore-next-line
    return [
      FieldMapConfiguration::class,
      FieldMapFactory::class
    ];
  }
}
