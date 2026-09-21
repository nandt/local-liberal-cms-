<?php

namespace Drupal\unicorn_opinions;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface
{
  use PermissionsTrait;

  case Administer = "edit opinions section";

  protected function getTitle(): string {
    return match ($this) {
      self::Administer => "Can administer opinions section",
    };
  }
}
