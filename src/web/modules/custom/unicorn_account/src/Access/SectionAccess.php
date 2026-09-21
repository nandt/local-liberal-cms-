<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\unicorn_account\Permissions\Permissions;

final class SectionAccess {

  /**
   * Grants view/update/delete on section entities per the dedicated permission.
   */
  public function access(string $operation, AccountInterface $account): AccessResultInterface {
    $permission = match ($operation) {
      'view' => Permissions::ViewSection->value,
      'update' => Permissions::UpdateSection->value,
      'delete' => Permissions::DeleteSection->value,
      default => NULL,
    };
    if ($permission === NULL) {
      return AccessResult::neutral();
    }

    return AccessResult::allowedIfHasPermission($account, $permission);
  }

  public function createAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, Permissions::CreateSection->value);
  }

}
