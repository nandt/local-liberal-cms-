<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Exposes the creation timestamp of a term, taken from its first revision.
 *
 * @extends FieldItemList<\Drupal\Core\Field\FieldItemInterface>
 */
final class TermCreatedField extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue(): void {
    $tid = (int) $this->getEntity()->id();
    if (!$tid) {
      return;
    }

    $created = \Drupal::database()->select('taxonomy_term_revision', 'r')
      ->fields('r', ['revision_created'])
      ->condition('r.tid', $tid)
      ->orderBy('r.revision_id')
      ->range(0, 1)
      ->execute()
      ?->fetchField();

    $this->list[0] = $this->createItem(0, ['value' => (int) $created]);
  }

}
