<?php

namespace Drupal\unicorn_ai\Permissions;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface {
  use PermissionsTrait;

  case SummarizeTranslation = 'summarize translation';

  protected function getTitle(): string {
    return match ($this) {
      self::SummarizeTranslation => 'Allow Summarizing and Translation',
    };
  }

}
