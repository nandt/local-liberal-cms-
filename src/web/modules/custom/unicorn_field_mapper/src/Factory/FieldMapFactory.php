<?php

declare(strict_types=1);

namespace Drupal\unicorn_field_mapper\Factory;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_field_mapper\Configuration\FieldMapConfiguration;
use Drupal\unicorn_field_mapper\Contracts\FieldMapInterface;
use Drupal\unicorn_field_mapper\Support\NodeFieldMap;
use InvalidArgumentException;

final readonly class FieldMapFactory {

  public function __construct(
    private FieldMapConfiguration $fieldConfig,
  ) {}

  /**
   * @throws InvalidArgumentException
   */
  public function forEntity(EntityInterface $entity): FieldMapInterface {
    if($entity instanceof NodeInterface) {
      return new NodeFieldMap($entity, $this->fieldConfig);
    }

    throw new InvalidArgumentException('Unsupported entity type');
  }
}
