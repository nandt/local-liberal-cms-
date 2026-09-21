<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Dto\Filters;

use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Http\RequestParams;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchFilters {

  public function __construct(
    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(min: 3)]
    public ?string $query = null,

    #[Assert\Choice(choices: ['created'])]
    public ?string $sort = null,

    #[Assert\Choice(choices: ['ASC', 'DESC'])]
    public string $direction = 'DESC',

    #[Assert\PositiveOrZero]
    public int $page = 0,

    #[Assert\Range(min: 1, max: 100)]
    public int $perPage = 25,
  ) {}

  public static function fromRequest(Request $request): self {
    $params = RequestParams::createFromRequest($request);

    return new self(
      query: $params->get('q'),
      sort: $params->get('sort'),
      direction: strtoupper((string) $params->get('direction', 'DESC')),
      page: (int) $params->getInt('page', 0),
      perPage: (int) $params->getInt('per_page', 25),
    );
  }

}
