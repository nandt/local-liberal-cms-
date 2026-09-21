<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_socials_post\Configuration\FacebookConfig;
use Drupal\unicorn_socials_post\Configuration\XConfig;
use Drupal\unicorn_socials_post\Resources\SocialsPostResource;
use Drupal\unicorn_socials_post\Services\SocialTasks;

class UnicornSocialsPostServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
    // @phpstan-ignore-next-line
    return [
      SocialTasks::class,
      SocialsPostResource::class,
      FacebookConfig::class,
      XConfig::class
    ];
  }
}
