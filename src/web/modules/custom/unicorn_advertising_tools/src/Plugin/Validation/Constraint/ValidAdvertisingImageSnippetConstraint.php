<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'ValidAdvertisingImageSnippet',
  label: new TranslatableMarkup('Valid advertising image snippet', [], ['context' => 'Validation']),
  type: 'entity:node'
)]
final class ValidAdvertisingImageSnippetConstraint extends SymfonyConstraint {

  public string $message = 'Snippet is not valid. Please review it and try again';

}
