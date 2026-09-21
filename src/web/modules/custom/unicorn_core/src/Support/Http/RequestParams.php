<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Support\Http;

use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpFoundation\ParameterBag;

class RequestParams
{
  protected ParameterBag $parameters;

  /**
   * @param array<array-key, mixed> $data
   */
  public function __construct(array $data = [])
  {
    $this->parameters = new ParameterBag($data);
  }

  public static function createFromRequest(Request $request): self
  {
    $instance = new self();
    $instance->parameters = new ParameterBag($request->input());

    return $instance;
  }

  public function params(): ParameterBag
  {
    return $this->parameters;
  }

  public function get(string $key, ?string $default = null): mixed
  {
    $exists = $this->has($key);

    return $exists ? $this->params()->get($key) : $default;
  }

  public function getInt(string $key, ?int $default = null): ?int
  {
    $exists = $this->has($key);


    return $exists ? $this->params()->getInt($key) : $default;
  }

  public function getFloat(string $key, ?float $default = null): ?float
  {
    $exists = $this->has($key);

    return $exists ? (float) $this->params()->get($key) : $default;
  }

  public function getBool(string $key, ?bool $default = null): ?bool
  {
    $exists = $this->has($key);

    return $exists ? $this->params()->getBoolean($key) : $default;
  }

  /**
   * @throws \DateMalformedStringException
   */
  public function getDateTime(string $key, ?DateTimeImmutable $default = null, ?DateTimeZone $timezone = null): ?DateTimeImmutable
  {
    if (!$this->has($key)) {
      return $default;
    }

    $value = $this->params()->get($key);

    return is_numeric($value)
      ? new DateTimeImmutable("@{$value}")
      : new DateTimeImmutable((string) $value, $timezone);
  }

  /**
   * @template TEnum of BackedEnum
   *
   * @param class-string<TEnum> $enumClass
   * @param TEnum|null $default
   *
   * @return TEnum|null
   */
  public function getEnum(string $key, string $enumClass, ?BackedEnum $default = null): ?BackedEnum
  {
    return $this->has($key) ? $this->params()->getEnum($key, $enumClass, $default) : $default;
  }

  private function has(string $key): bool
  {
    return $this->params()->has($key);
  }
}
