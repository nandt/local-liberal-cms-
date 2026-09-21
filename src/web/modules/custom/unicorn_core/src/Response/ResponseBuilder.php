<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Response;

use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

readonly class ResponseBuilder
{
  /**
   * @param array<string, string> $headers
   * @param bool $json Whether $data is already a JSON-encoded string.
   */
    private function __construct(
        private mixed $data = null,
        private int $status = Response::HTTP_OK,
        private array $headers = [],
        private bool $json = false
    ) {
    }

  /**
   * @param array<string, string> $headers
   * @param bool $json Whether $data is already a JSON-encoded string.
   */
    public static function make(
        mixed $data = null,
        int $status = Response::HTTP_OK,
        array $headers = [],
        bool $json = false
    ): self {
        return new self($data, $status, $headers, $json);
    }

    public function json(): JsonResponse
    {
        return new JsonResponse(...$this->getResponseData());
    }

    public function cacheableJson(): CacheableJsonResponse
    {
        return new CacheableJsonResponse(...$this->getResponseData());
    }

  /**
   * @return array{
   *   data: mixed,
   *   status: int,
   *   headers: array<string, string>,
   *   json: bool
   * }
   */
    private function getResponseData(): array
    {
        return [
        'data' => $this->data,
        'status' => $this->status,
        'headers' => $this->headers,
        'json' => $this->json
        ];
    }
}
