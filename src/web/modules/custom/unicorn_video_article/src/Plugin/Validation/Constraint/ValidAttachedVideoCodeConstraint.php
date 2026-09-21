<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'ValidAttachedVideoCode',
  label: new TranslatableMarkup('Valid attached video code', [], ['context' => 'Validation']),
  type: 'entity:node'
)]
final class ValidAttachedVideoCodeConstraint extends SymfonyConstraint {

  public string $message = 'Please enter a valid video code.';

}
