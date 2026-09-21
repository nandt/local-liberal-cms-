<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Dto\Filters;

use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Http\RequestParams;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdvertisingToolsFilters {

  public function __construct (

    #[Assert\Positive]
    public ?int $entityId = null,

    #[Assert\Type(type: 'boolean')]
    public ?bool $state = null,

    #[Assert\Type(type: 'string')]
    public ?string $nodeTitle = null,

    #[Assert\Choice(choices: ['history_date', 'promoted_date', 'id'])]
    public ?string $sort = null,

    #[Assert\Choice(choices: ['ASC', 'DESC'])]
    public string $direction = 'DESC',

    #[Assert\PositiveOrZero]
    #[Assert\Type(type: 'integer')]
    public int $page = 0,

    #[Assert\Range(min: 1, max: 100)]
    public int $perPage = 25,
  ) {}

  public static function fromRequest(Request $request): self {
    $params = RequestParams::createFromRequest($request);

    return new self(
      entityId: $params->getInt('entity_id'),
      state: $params->getBool('state'),
      nodeTitle: $params->get('title'),
      sort: $params->get('sort'),
      direction: strtoupper((string) $params->get('direction', 'DESC')),
      page: (int) $params->getInt('page', 0),
      perPage: (int) $params->getInt('per_page', 25),
    );
  }

}
