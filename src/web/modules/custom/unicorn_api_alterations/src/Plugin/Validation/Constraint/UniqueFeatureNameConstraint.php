<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'UniqueFeatureName',
  label: new TranslatableMarkup('Unique Feature internal name', [], ['context' => 'Validation']),
  type: 'entity:taxonomy_term'
)]
final class UniqueFeatureNameConstraint extends SymfonyConstraint {

  public string $message = 'This name is already in use.';

}
