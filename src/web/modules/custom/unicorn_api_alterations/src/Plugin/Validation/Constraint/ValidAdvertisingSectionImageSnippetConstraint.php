<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'ValidAdvertisingSectionImageSnippet',
  label: new TranslatableMarkup('Valid advertising section image snippet', [], ['context' => 'Validation']),
  type: 'entity:section'
)]
final class ValidAdvertisingSectionImageSnippetConstraint extends SymfonyConstraint {

  public string $message = 'Snippet is not valid. Please review it and try again';

}
