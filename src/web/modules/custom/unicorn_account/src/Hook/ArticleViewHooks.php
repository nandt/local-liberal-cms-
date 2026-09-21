<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_account\Access\ArticleViewAccess;

final readonly class ArticleViewHooks {

  public function __construct(
    private ArticleViewAccess $articleViewAccess,
  ) {}

  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $op, AccountInterface $account): AccessResultInterface {
    return $this->articleViewAccess->view($node, $op, $account);
  }

}
