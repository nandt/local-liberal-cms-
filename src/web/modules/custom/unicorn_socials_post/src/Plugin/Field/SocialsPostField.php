<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Plugin\Field;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\unicorn_socials_post\Cache\SocialsPostFieldCache;
use Drupal\unicorn_socials_post\Resources\SocialsPostResource;

/**
 * Computes per-platform social sharing values for a node.
 *
 * @extends FieldItemList<FieldItemInterface>
 */
class SocialsPostField extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue(): void {
    $socialsPostResource = \Drupal::service(SocialsPostResource::class);
    $entity = $this->getEntity();

    // get item from cache otherwise compute value from the start
    $data = SocialsPostFieldCache::get((int) $entity->id());

    $item = $socialsPostResource->toAllPlatformsSingle($data ?? []);
    $this->list[0] = $this->createItem(0, $item);
  }

}
