<?php

declare(strict_types=1);

namespace Drupal\unicorn_field_mapper\Support;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_core\Accessors\Data;
use Drupal\unicorn_field_mapper\Configuration\FieldMapConfiguration;
use Drupal\unicorn_field_mapper\Contracts\FieldMapInterface;

final readonly class NodeFieldMap implements FieldMapInterface {

  public function __construct(
    private NodeInterface $node,
    private FieldMapConfiguration $fieldConfig
  ) {}

  /**
   * @return FieldItemListInterface<FieldItemInterface>|null
   */
  public function getField(string $field): ?FieldItemListInterface {
    $mappedField = $this->getMap($field);

    if(!$mappedField) {
      return null;
    }

    if(!$this->node->hasField($mappedField)) {
      return null;
    }

    return $this->node->get($mappedField);
  }

  private function getMap(string $field): ?string {
    $bundle = $this->node->bundle();
    $map = $this->fieldConfig->getFieldConfiguration();

    return Data::get($map, "{$bundle}.{$field}");
  }
}
