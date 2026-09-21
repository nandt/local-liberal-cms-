<?php

declare(strict_types=1);

namespace Drupal\unicorn_client_preview\Permissions;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface {
  use PermissionsTrait;

  case ViewClientPreview = 'view articles flagged for client preview';

  protected function getTitle(): string {
    return match ($this) {
      self::ViewClientPreview => 'View Sponsored Articles flagged for client preview',
    };
  }

  protected function getDescription(): string {
    return match ($this) {
      self::ViewClientPreview => 'Allows viewing individual draft Sponsored Articles when Visible to client is enabled.',
    };
  }

}
