<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Support;

use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;

final class ItemNodeIdResolver {

  /**
   * @param ItemInterface<string, FieldInterface> $item
   */
  public function getNodeId(ItemInterface $item): int {
    [, $rawId] = Utility::splitCombinedId($item->getId());
    [$nodeId] = explode(':', (string) $rawId, 2);

    return (int) $nodeId;
  }

}
