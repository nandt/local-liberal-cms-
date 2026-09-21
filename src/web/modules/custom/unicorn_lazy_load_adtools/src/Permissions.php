<?php

declare(strict_types=1);

namespace Drupal\unicorn_lazy_load_adtools;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface {

  use PermissionsTrait;

  case CHANGE_LAZY_LOAD_ADTOOLS_SETTINGS = 'change lazy load adtools settings';

  protected function getTitle(): string {
    return match ($this) {
      self::CHANGE_LAZY_LOAD_ADTOOLS_SETTINGS => 'Change the lazy loading settings',
    };
  }

}
