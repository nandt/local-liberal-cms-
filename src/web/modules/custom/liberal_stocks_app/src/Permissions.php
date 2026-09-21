<?php

namespace Drupal\liberal_stocks_app;

use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string
{
  use PermissionsTrait;

  case SendNotifications = "send stocks notifications";

  protected function getTitle(): string {
    return match ($this) {
      self::SendNotifications => "Can send stocks notifications",
    };
  }
}
