<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\unicorn_api_alterations\Controller\SectionTemplatesController;

/**
 * Resolves a Section template machine ID to its dashboard-facing label.
 *
 * @extends FieldItemList<\Drupal\Core\Field\FieldItemInterface>
 */
final class SectionTemplateLabelField extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue(): void {
    $entity = $this->getEntity();
    if (!$entity->hasField('template')) {
      return;
    }

    $template = (string) $entity->get('template')->value;
    $labels = array_column(SectionTemplatesController::TEMPLATES, 'label', 'id');

    if (isset($labels[$template])) {
      $this->list[0] = $this->createItem(0, ['value' => $labels[$template]]);
    }
  }

}
