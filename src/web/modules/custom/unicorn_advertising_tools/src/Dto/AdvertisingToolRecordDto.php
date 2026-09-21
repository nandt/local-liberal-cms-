<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Dto;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Http\RequestParams;
use LogicException;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdvertisingToolRecordDto {

  public function __construct(
    #[Assert\NotBlank (groups: ['create'])]
    #[Assert\Positive]
    public ?int $entityId,

    #[Assert\NotBlank (groups: ['create'])]
    #[Assert\PositiveOrZero]
    public ?int $targetImpressions,

    #[Assert\NotNull (groups: ['create'])]
    public ?DateTimeImmutable $startDate,

    #[Assert\NotNull (groups: ['create'])]
    public ?DateTimeImmutable $endDate,

    #[Assert\NotNull (groups: ['create'])]
    #[Assert\IsTrue (groups: ['create'])]
    public ?bool $state,

    #[Assert\NotBlank (groups: ['create'])]
    #[Assert\Range(min: 1, max: 10)]
    public ?int $priority,
  ) {}

  /**
   * @throws DateMalformedStringException
   */
  public static function fromRequest(Request $request): self {
    $params = RequestParams::createFromRequest($request);

    return new self(
      $params->getInt('entity_id'),
      $params->getInt('target_impressions'),
      $params->getDateTime('start_date', null, new DateTimeZone('UTC')),
      $params->getDateTime('end_date', null, new DateTimeZone('UTC')),
      $params->getBool('state'),
      $params->getInt('priority'),
    );
  }

  public static function fromPatchRequest(Request $request): self {
    $params = RequestParams::createFromRequest($request);

    return new self(
      null,
      $params->getInt('target_impressions'),
      $params->getDateTime('start_date', null, new DateTimeZone('UTC')),
      $params->getDateTime('end_date', null, new DateTimeZone('UTC')),
      $params->getBool('state'),
      $params->getInt('priority'),
    );
  }

  /**
   * @return array{
   *   entity_id: int,
   *   target_impressions: int,
   *   start_date: int,
   *   end_date: int,
   *   state: int,
   *   priority: int,
   *}
   */
  public function toStoreRecord(): array {

    /**
     * @todo: write better for phpstan if possible
     * necessary evil because we used the same DTO for update and create to avoid
     * maintaining 2 DTOs.
     */
    if (
      is_null($this->entityId) ||
      is_null($this->targetImpressions) ||
      is_null($this->startDate) ||
      is_null($this->endDate) ||
      is_null($this->state) ||
      is_null($this->priority)
    ) {
      throw new LogicException('All fields must be set.');
    }

    return [
      'entity_id' => $this->entityId,
      'target_impressions' => $this->targetImpressions,
      'start_date' => $this->startDate->getTimestamp(),
      'end_date' => $this->endDate->getTimestamp(),
      'state' => (int) $this->state,
      'priority' => $this->priority,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function toUpdateRecord(): array {
    $data = [
      'target_impressions' => $this->targetImpressions,
      'start_date' => $this->startDate?->getTimestamp(),
      'end_date' => $this->endDate?->getTimestamp(),
      'state' => $this->state === null ? null : (int) $this->state,
      'priority' => $this->priority,
    ];

    $collect = Collection::wrap($data);
    return $collect->filter(static fn($item): bool => !is_null($item))->toArray();
  }

}
