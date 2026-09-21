<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Controllers;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_core\Resources\PaginatorResource;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Paginator;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Drupal\unicorn_search\Dto\Filters\SearchFilters;
use Drupal\unicorn_search\Repository\SearchRepository;
use Drupal\unicorn_search\Resource\SearchResource;

final class SearchController extends ControllerBase {

  public function __construct(
    private readonly SearchRepository $searchRepository,
    private readonly SearchResource $searchResource,
    private readonly PaginatorResource $paginatorResource,
    private readonly ExtendedValidator $validator,
  ) {}

  public function index(Request $request): CacheableJsonResponse {
    $filters = SearchFilters::fromRequest($request);
    $this->validator->validateAndThrow($filters);

    $result = $this->searchRepository->search($filters);

    $paginator = new Paginator(
      total: $result->get('total'),
      count: count($result->get('items')),
      page: $filters->page,
      perPage: $filters->perPage,
    );

    $response = ResponseBuilder::make([
      'data' => $this->searchResource->toApiCollection($result->get('items')),
      'pager' => $this->paginatorResource->toApi($paginator),
    ])->cacheableJson();

    $cacheMetadata = new CacheableMetadata()
      ->addCacheTags(['node_list'])
      ->addCacheContexts(['url.query_args']);

    $response->addCacheableDependency($cacheMetadata);
    return $response;
  }

}
