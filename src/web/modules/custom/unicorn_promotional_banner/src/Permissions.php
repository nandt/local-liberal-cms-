<?php

declare(strict_types=1);

namespace Drupal\unicorn_promotional_banner;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface
{
  use PermissionsTrait;

  case Administer = "administer promo banner";
  case View = "view promo banner";

  protected function getTitle(): string
  {
    return match ($this) {
      self::Administer => "administer promo banner",
      self::View => "view promo banner",
    };
  }

}
