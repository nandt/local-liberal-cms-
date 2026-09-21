<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'ValidVideoSourceCode',
  label: new TranslatableMarkup('Valid video source code', [], ['context' => 'Validation']),
  type: 'entity:node'
)]
final class ValidVideoSourceCodeConstraint extends SymfonyConstraint {

  public string $message = 'Please enter a valid cloudflare code';

}
