<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Exposes the legacy /oblation/{slug} URL kept from liberal.gr.
 *
 * The alias lives under the legacy /oblation/{tid} system path, which the core
 * path field never looks up because it only resolves the canonical term route.
 *
 * @extends FieldItemList<\Drupal\Core\Field\FieldItemInterface>
 */
final class FeatureLegacyUrlField extends FieldItemList {

  use ComputedItemListTrait;

  private const string LEGACY_PATH_PREFIX = '/oblation/';

  protected function computeValue(): void {
    $entity = $this->getEntity();
    $tid = (int) $entity->id();
    if (!$tid) {
      return;
    }

    $system_path = self::LEGACY_PATH_PREFIX . $tid;
    $langcode = $entity->language()->getId();

    $alias = \Drupal::database()->select('path_alias', 'a')
      ->fields('a', ['alias'])
      ->condition('a.path', $system_path)
      ->condition('a.langcode', [$langcode, 'und'], 'IN')
      ->condition('a.status', 1)
      ->orderBy('a.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ?->fetchField();

    $this->list[0] = $this->createItem(0, ['value' => $alias ?: $system_path]);
  }

}
