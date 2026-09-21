<?php

namespace Drupal\unicorn_preview_nodes\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

class NodeAccessHooks {

  #[Hook('node_access')]
  public function nodeAccess(EntityInterface $node, $op, AccountInterface $account) {
    if ($op === 'view' && !$node->isPublished() && $account->hasPermission('view unpublished for preview')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($node);
    }
    return AccessResult::neutral();
  }

}
