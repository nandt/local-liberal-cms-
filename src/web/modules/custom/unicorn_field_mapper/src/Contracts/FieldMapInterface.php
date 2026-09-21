<?php

declare(strict_types=1);

namespace Drupal\unicorn_field_mapper\Contracts;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;

interface FieldMapInterface {

  /**
   * @return FieldItemListInterface<FieldItemInterface>|null
   */
  public function getField(string $field): ?FieldItemListInterface;
}
