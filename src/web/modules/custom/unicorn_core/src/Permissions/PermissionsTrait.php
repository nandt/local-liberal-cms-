<?php

namespace Drupal\unicorn_core\Permissions;

use Drupal\unicorn_core\Support\Collection;

trait PermissionsTrait
{
  abstract protected function getTitle(): string;

  protected function getDescription(): ?string {
    return null;
  }

  /**
   * @return array<string, array{title: string, description: ?string}>
   */
  public static function getAll(): array {
    return Collection::wrap(self::cases())->mapWithKeys(fn ($permission): array => [$permission->value => [
        'title' => $permission->getTitle(),
        'description' => $permission->getDescription(),
      ]])->toArray();
  }
}
