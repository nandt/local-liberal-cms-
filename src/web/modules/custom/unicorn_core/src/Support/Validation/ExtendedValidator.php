<?php

namespace Drupal\unicorn_core\Support\Validation;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Validation\ConstraintValidatorFactory;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * An extended validator that decorates an existing ValidatorInterface.
 *
 * Builds its own plain Symfony validator instead of going through Drupal
 * core's BasicRecursiveValidatorFactory
 */
class ExtendedValidator implements ValidatorInterface
{
  private readonly ValidatorInterface $decoratedValidator;

  public function __construct(ClassResolverInterface $classResolver)
  {
    $this->decoratedValidator = Validation::createValidatorBuilder()
      ->setConstraintValidatorFactory(new ConstraintValidatorFactory($classResolver))
      ->enableAttributeMapping()
      ->getValidator();
  }

  public function validate(mixed $value, Constraint|array|null $constraints = null, string|GroupSequence|array|null $groups = null): ConstraintViolationListInterface
  {
    return $this->decoratedValidator->validate($value, $constraints, $groups);
  }

  public function getMetadataFor(mixed $value): MetadataInterface
  {
    return $this->decoratedValidator->getMetadataFor($value);
  }

  public function hasMetadataFor(mixed $value): bool
  {
    return $this->decoratedValidator->hasMetadataFor($value);
  }

  public function validateProperty(
    object $object,
    string $propertyName,
    string|GroupSequence|array|null $groups = null
  ): ConstraintViolationListInterface {
    return $this->decoratedValidator->validateProperty($object, $propertyName, $groups);
  }

  public function validatePropertyValue(
    object|string $objectOrClass,
    string $propertyName,
    mixed $value,
    string|GroupSequence|array|null $groups = null
  ): ConstraintViolationListInterface {
    return $this->decoratedValidator->validatePropertyValue($objectOrClass, $propertyName, $value, $groups);
  }

  public function startContext(): ContextualValidatorInterface
  {
    return $this->decoratedValidator->startContext();
  }

  public function inContext(ExecutionContextInterface $context): ContextualValidatorInterface
  {
    return $this->decoratedValidator->inContext($context);
  }
  /**
   * @param mixed $value The value to validate
   * @param Constraint|Constraint[]|null $constraints The constraint(s) to validate against
   * @param array<int, string|GroupSequence>|null $groups The validation groups to validate. If none is given, "Default" is assumed
   */
  public function validateAndThrow(mixed $value, Constraint|array|null $constraints = null, ?array $groups = null): void
  {
    $violations = $this->validate($value, $constraints, $groups);
    if (count($violations) <= 0) {
      return;
    }
    $errors = [];

    foreach ($violations as $violation) {
      $field = new UnicodeString($violation->getPropertyPath())
        ->replace('[', '.')
        ->replace(']', '')
        ->trimStart('.')
        ->toString();

      $errors[$field][] = $violation->getMessage();
    }
    throw new ValidationFailedException(['errors' => $errors], $violations);
  }
}
