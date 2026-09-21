<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface
{
  use PermissionsTrait;

  case Share = "share on social";

  protected function getTitle(): string
  {
    return match ($this) {
      self::Share => "Share on Social Media ( Facebook, X etc )",
    };
  }

  protected function getDescription(): string {
    return match ($this) {
      self::Share => "Allows users to share content to configured social media platforms.",
    };
  }

}

