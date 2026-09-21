<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\node\NodeInterface;
use Drupal\unicorn_computed_fields\Enum\ArticleStatus;

/**
 * Computes the publishing state exposed for an Article.
 *
 * @extends FieldItemList<\Drupal\Core\Field\FieldItemInterface>
 */
final class ArticleStatusField extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue(): void {
    $entity = $this->getEntity();
    if (!$entity instanceof NodeInterface) {
      return;
    }

    $this->list[0] = $this->createItem(0, [
      'value' => ArticleStatus::fromArticle($entity)->value,
    ]);
  }

}
