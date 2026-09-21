<?php

declare(strict_types=1);

namespace Drupal\unicorn_client_preview\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_client_preview\Permissions\Permissions;

final class ClientPreviewAccess {

  private const string ARTICLE_BUNDLE = 'article_liberal';

  private const string CLIENT_PREVIEW_FIELD = 'field_show_to_client';

  private const string ARTICLE_TYPE_FIELD = 'field_eidos_arthrou';

  private const string SPONSORED_ARTICLE_TYPE = 'Διαφημιστικό Άρθρο';

  public function check(NodeInterface $node, string $op, AccountInterface $account): AccessResultInterface {
    $result = AccessResult::neutral()
      ->cachePerPermissions()
      ->addCacheableDependency($node);

    if (
      $op === 'view'
      && $node->bundle() === self::ARTICLE_BUNDLE
      && !$node->isPublished()
      && $account->hasPermission(Permissions::ViewClientPreview->value)
      && $node->get(self::CLIENT_PREVIEW_FIELD)->value
    ) {
      foreach ($node->get(self::ARTICLE_TYPE_FIELD)->referencedEntities() as $article_type) {
        $result->addCacheableDependency($article_type);
        if ($article_type->label() === self::SPONSORED_ARTICLE_TYPE) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->addCacheableDependency($node)
            ->addCacheableDependency($article_type);
        }
      }
    }

    return $result;
  }

}
