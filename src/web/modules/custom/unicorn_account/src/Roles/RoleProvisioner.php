<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Roles;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;

final readonly class RoleProvisioner {

  private const array OAUTH_BASELINE = ['grant simple_oauth codes'];

  /**
   * @param list<string> $availablePermissions
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private array $availablePermissions,
  ) {}

  public function provisionAll(): void {
    foreach (Role::cases() as $role) {
      $this->provision($role);
    }
  }

  public function provision(Role $role): void {
    $storage = $this->entityTypeManager->getStorage('user_role');

    $entity = $storage->load($role->value);
    if (!$entity instanceof RoleInterface) {
      $entity = $storage->create([
        'id' => $role->value,
        'label' => $role->label(),
      ]);
    }

    if ($role->isAdmin()) {
      $entity->setIsAdmin(TRUE);
      $entity->save();

      return;
    }

    foreach ($this->permissionsFor($role) as $permission) {
      $entity->grantPermission($permission);
    }

    $entity->save();
  }

  /**
   * @return list<string>
   */
  public function permissionsFor(Role $role): array {
    return array_values(array_filter(
      $this->rawPermissions($role),
      fn (string $permission): bool => in_array($permission, $this->availablePermissions, TRUE),
    ));
  }

  /**
   * @return list<string>
   */
  public function skippedFor(Role $role): array {
    return array_values(array_filter(
      $this->rawPermissions($role),
      fn (string $permission): bool => !in_array($permission, $this->availablePermissions, TRUE),
    ));
  }

  /**
   * @return list<string>
   */
  private function rawPermissions(Role $role): array {
    $permissions = array_fill_keys(self::OAUTH_BASELINE, TRUE);
    foreach ($role->capabilities() as $entry) {
      [$capability, $actions] = $entry instanceof Capability ? [$entry, NULL] : $entry;
      foreach ($capability->permissions($actions) as $permission) {
        $permissions[$permission] = TRUE;
      }
    }

    return array_keys($permissions);
  }

}
