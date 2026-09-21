<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueFeatureNameConstraintValidator extends ConstraintValidator {

  private const string FEATURE_VOCABULARY = 'oblations';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UniqueFeatureNameConstraint || !$value instanceof FieldItemListInterface) {
      return;
    }

    $feature = $value->getEntity();
    if (!$feature instanceof TermInterface || $feature->bundle() !== self::FEATURE_VOCABULARY) {
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
      ->condition('vid', self::FEATURE_VOCABULARY)
      ->condition('name', $this->database->escapeLike($name), 'LIKE')
      ->range(0, 1);

    if (!$feature->isNew()) {
      $query->condition('tid', $feature->id(), '<>');
    }

    if ($query->execute()) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }

}
