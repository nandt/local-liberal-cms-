<?php

declare(strict_types=1);

namespace Drupal\unicorn_sources\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueSourceNameConstraintValidator extends ConstraintValidator {

  private const string SOURCE_VOCABULARY = 'piges_arthron';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UniqueSourceNameConstraint || !$value instanceof FieldItemListInterface) {
      return;
    }

    $source = $value->getEntity();
    if (!$source instanceof TermInterface || $source->bundle() !== self::SOURCE_VOCABULARY) {
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
      ->condition('vid', self::SOURCE_VOCABULARY)
      ->condition('name', $this->database->escapeLike($name), 'LIKE')
      ->range(0, 1);

    if (!$source->isNew()) {
      $query->condition('tid', $source->id(), '<>');
    }

    if ($query->execute()) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }

}
