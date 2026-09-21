<?php

namespace Drupal\unicorn_core\Support\Http;

use Drupal\unicorn_core\Accessors\Data;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest
{
  /**
   * @var InputBag<bool|float|int|string>|null
   */
  protected ?InputBag $json = null;

  #[\Override]
  public static function createFromGlobals(): static
  {
    $request = SymfonyRequest::createFromGlobals();

    return static::createFromRequest($request);
  }

  public static function createFromRequest(SymfonyRequest $request): static
  {
    /** @phpstan-ignore-next-line  */
    $request = new static(
      $request->query->all(),
      $request->request->all(),
      $request->attributes->all(),
      $request->cookies->all(),
      $request->files->all(),
      $request->server->all(),
      $request->getContent(true)
    );

    $request->headers->replace($request->headers->all());

    return $request;
  }

  public function getUriWithoutQuery(): string
  {
    return rtrim(rtrim($this->getUri(), "?{$this->getQueryString()}"), '/');
  }

  /**
   * @return InputBag<bool|float|int|string>
   */
  protected function json(): InputBag
  {
    return $this->json ??= new InputBag(json_decode($this->getContent(), true, 512, JSON_ERROR_NONE) ?: []);
  }

  /**
   * Get an input value from the request.
   *
   * When called without arguments, returns all input as an array.
   * When called with a key, returns the value for that key or the default.
   *
   * @template TDefault
   *
   * @param string|null $arg The input key to retrieve
   * @param TDefault $default Default value if key doesn't exist
   *
   * @return ($arg is null ? array<string, mixed> : mixed|TDefault)
   */
  public function input(?string $arg = null, mixed $default = null)
  {
    $input = $this->getInputSource()->all() + $this->query->all();

    if (is_null($arg)) {
      return $input;
    }

    return Data::get($input, $arg, $default);
  }

  /**
   * @return InputBag<bool|float|int|string>
   */
  protected function getInputSource(): InputBag
  {
    if ($this->isJson()) {
      return $this->json();
    }

    return in_array($this->getRealMethod(), ['GET', 'HEAD'])
      ? $this->query
      : $this->request;
  }

  protected function isJson(): bool
  {
    $contentType = (string) $this->headers->get('CONTENT_TYPE', '');

    return str_contains($contentType, '/json')
      || str_contains($contentType, '+json');
  }

  /**
   * @param array<string, mixed> $input
   */
  public function replace(array $input): self
  {
    $this->getInputSource()->replace($input);

    return $this;
  }

  /**
   * @param array<string,mixed> $input
   */
  public function merge(array $input): self
  {
    foreach ($input as $key => $value) {
      $this->getInputSource()->set($key, $value);
    }

    return $this;
  }
}
