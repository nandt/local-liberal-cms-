<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface
{
  use PermissionsTrait;

  case Administer = "administer advertising tools page";
  case View = "view advertising tools page";

  protected function getTitle(): string
  {
    return match ($this) {
      self::Administer => "administer the advertising tools page",
      self::View => "view the advertising tools page",
    };
  }

}
