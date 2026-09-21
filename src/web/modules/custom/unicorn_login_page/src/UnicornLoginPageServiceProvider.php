<?php

declare(strict_types=1);

namespace Drupal\unicorn_login_page;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_login_page\Configuration\LoginPageConfig;

class UnicornLoginPageServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array
  {
    // @phpstan-ignore-next-line
    return [
      LoginPageConfig::class,
    ];
  }

}
