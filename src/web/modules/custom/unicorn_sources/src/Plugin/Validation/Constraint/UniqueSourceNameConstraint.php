<?php

declare(strict_types=1);

namespace Drupal\unicorn_sources\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'UniqueSourceName',
  label: new TranslatableMarkup('Unique Source name', [], ['context' => 'Validation']),
  type: 'entity:taxonomy_term'
)]
final class UniqueSourceNameConstraint extends SymfonyConstraint {

  public string $message = 'This source already exists. Please add a different name.';

}
