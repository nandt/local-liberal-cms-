<?php

declare(strict_types=1);

namespace Drupal\unicorn_refresh_on_viewable;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface {

  use PermissionsTrait;

  case ChangeRefreshOnViewableSettings = 'change refresh on viewable settings';

  protected function getTitle(): string {
    return match ($this) {
      self::ChangeRefreshOnViewableSettings => 'Change the Refresh on Viewable settings',
    };
  }

}
