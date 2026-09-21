<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_account\Permissions\Permissions;

final class ArticleViewAccess {

  private const string BUNDLE = 'article_liberal';

  /**
   * Read-only view on any article, published or not, for holders of the
   * dedicated permission. Used for the Commercial role's article overview.
   */
  public function view(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    if ($node->bundle() !== self::BUNDLE || $operation !== 'view') {
      return AccessResult::neutral();
    }

    return AccessResult::allowedIfHasPermission($account, Permissions::ViewAnyArticle->value)
      ->cachePerPermissions();
  }

}
