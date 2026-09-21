<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'UniqueEpisode',
  label: new TranslatableMarkup('Unique Episode In Series', [], ['context' => 'Validation']),
  type: 'entity:node'
)]
final class UniqueEpisodeConstraint extends SymfonyConstraint {

  public string $message = 'This episode number has already been assigned. Try another one.';

}
