<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Schema;

abstract class BaseSchema {

  final public function __construct(
  ) {}

  public static function make(): static {
    return new static();
  }

  final public function getSchema(): array {
    return $this->schema();
  }

  abstract protected function schema(): array;

}
