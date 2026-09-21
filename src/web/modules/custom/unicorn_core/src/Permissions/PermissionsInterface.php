<?php

namespace Drupal\unicorn_core\Permissions;

interface PermissionsInterface
{
  /**
   * @return array<string, array{title: string, description: ?string}>
   */
  public static function getAll(): array;
}
