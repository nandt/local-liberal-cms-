<?php

declare(strict_types=1);

namespace Drupal\unicorn_series\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueSeriesNameConstraintValidator extends ConstraintValidator {

  private const string SERIES_VOCABULARY = 'series';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UniqueSeriesNameConstraint || !$value instanceof FieldItemListInterface) {
      return;
    }

    $series = $value->getEntity();
    if (!$series instanceof TermInterface || $series->bundle() !== self::SERIES_VOCABULARY) {
      return;
    }

    $name = $value->value;
    if (!is_string($name) || $name === '') {
      return;
    }

    $query = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', self::SERIES_VOCABULARY)
      ->condition('name', $this->database->escapeLike($name), 'LIKE')
      ->range(0, 1);

    if (!$series->isNew()) {
      $query->condition('tid', $series->id(), '<>');
    }

    if ($query->execute()) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }

}
