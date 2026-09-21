<?php

namespace Drupal\unicorn_core\Accessors;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use TypeError;

class Data
{
  protected static PropertyAccessorInterface $propertyAccessor;


  /**
   * @template TDefault
   *
   * Get value using dot notation for arrays and objects
   *
   * @param array<array-key, mixed>|object $data
   * @param int|string $key Dot notation path (e.g., 'user.profile.name')
   * @param TDefault $default Default value if path doesn't exist
   *
   * @return mixed|TDefault
   */
  public static function get(array|object $data, int|string $key, mixed $default = null): mixed
  {
    $path = explode('.', (string)$key);
    $iterationData = $data;

    try {
      foreach ($path as $segment) {
        $sanitizedData = is_array($iterationData) ? (object) $iterationData : $iterationData;
        $iterationData = static::accessor()->getValue($sanitizedData, $segment);
      }
    } catch (NoSuchPropertyException|UnexpectedTypeException|TypeError) {
      return $default;
    }

    return $iterationData ?? $default;
  }

  /**
   * Set value using dot notation
   *
   * @param mixed $data Array or object (by reference)
   * @param string $key Dot notation path
   * @param mixed $value Value to set
   */
  public static function set(mixed &$data, string $key, mixed $value): void
  {
    $path = explode('.', $key);
    $iterationData = $data;
    $sanitizedKey = '';

    try {
      foreach ($path as $segment) {
        $isArray = is_array($iterationData);
        $sanitizedSegment = $isArray ? "[{$segment}]" : "$segment";

        if ($sanitizedSegment !== $path[ array_key_last($path)]) {

          $iterationData = static::accessor()->getValue($iterationData, $sanitizedSegment);

        }
        $sanitizedKey .= $isArray ? $sanitizedSegment : ".$sanitizedSegment";

      }
    } catch (NoSuchPropertyException) {
      return;
    }

    static::accessor()->setValue($data, ltrim($sanitizedKey, '.'), $value);
  }

  /**
   * Check if key exists
   *
   * @param array<array-key, mixed>|object $data Array or object
   * @param string $key Dot notation path
   */
  public static function has(array|object $data, string $key): bool
  {
    $default = $key.static::class;
    return static::get($data, $key, $default) !== $default;
  }


  protected static function accessor(): PropertyAccessorInterface
  {
    return static::$propertyAccessor ??= PropertyAccess::createPropertyAccessorBuilder()
      ->enableMagicMethods()
      ->getPropertyAccessor();
  }

}
