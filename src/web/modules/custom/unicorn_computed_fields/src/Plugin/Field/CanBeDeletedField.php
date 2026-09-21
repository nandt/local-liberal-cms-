<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\unicorn_computed_fields\Service\DeletionEligibility;

/**
 * Tells the dashboard whether the Delete action is still available.
 *
 * @extends FieldItemList<\Drupal\Core\Field\FieldItemInterface>
 */
final class CanBeDeletedField extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue(): void {
    /** @var DeletionEligibility $eligibility */
    $eligibility = \Drupal::service(DeletionEligibility::class);

    $this->list[0] = $this->createItem(0, [
      'value' => $eligibility->isDeletable($this->getEntity()),
    ]);
  }

}
