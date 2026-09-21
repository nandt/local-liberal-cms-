<?php

declare(strict_types=1);

namespace Drupal\unicorn_client_preview\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_client_preview\Access\ClientPreviewAccess;

final readonly class NodeAccessHooks {

  public function __construct(
    private ClientPreviewAccess $clientPreviewAccess,
  ) {}

  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $op, AccountInterface $account): AccessResultInterface {
    return $this->clientPreviewAccess->check($node, $op, $account);
  }

}
