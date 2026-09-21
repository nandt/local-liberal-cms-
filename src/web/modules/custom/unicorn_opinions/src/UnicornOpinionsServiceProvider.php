<?php

declare(strict_types=1);

namespace Drupal\unicorn_opinions;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\unicorn_core\UnicornCoreServiceProvider;
use Drupal\unicorn_opinions\Resources\AdminOpinionsResource;
use Drupal\unicorn_opinions\Resources\OpinionsResource;

class UnicornOpinionsServiceProvider extends UnicornCoreServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
      // @phpstan-ignore-next-line
      return [
        OpinionsResource::class,
        AdminOpinionsResource::class,
      ];
  }

}
