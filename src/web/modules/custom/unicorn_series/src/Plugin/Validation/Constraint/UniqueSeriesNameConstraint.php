<?php

declare(strict_types=1);

namespace Drupal\unicorn_series\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'UniqueSeriesName',
  label: new TranslatableMarkup('Unique Series name', [], ['context' => 'Validation']),
  type: 'entity:taxonomy_term'
)]
final class UniqueSeriesNameConstraint extends SymfonyConstraint {

  public string $message = 'Unable to complete the action due to duplicate Name. Review the Series name and try again.';

}
