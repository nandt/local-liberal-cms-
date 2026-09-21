<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\unicorn_api_alterations\Controller\SectionTemplatesController;

/**
 * Resolves how many article slots a Feature's inner-page layout provides.
 *
 * @extends FieldItemList<\Drupal\Core\Field\FieldItemInterface>
 */
final class FeatureArticleSlotsField extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue(): void {
    $entity = $this->getEntity();
    if (!$entity->hasField('field_ob_page_layout')) {
      return;
    }

    $layout = (string) $entity->get('field_ob_page_layout')->value;
    $slots = array_column(SectionTemplatesController::TEMPLATES, 'articleSlots', 'id');

    $this->list[0] = $this->createItem(0, ['value' => (int) ($slots[$layout] ?? 0)]);
  }

}
