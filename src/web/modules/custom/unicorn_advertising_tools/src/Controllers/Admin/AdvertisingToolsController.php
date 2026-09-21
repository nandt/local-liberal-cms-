<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Controllers\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Dto\AdvertisingToolRecordDto;
use Drupal\unicorn_advertising_tools\Dto\Filters\AdvertisingToolsFilters;
use Drupal\unicorn_advertising_tools\Repository\AdvertisingToolsRepository;
use Drupal\unicorn_advertising_tools\Resource\AdvertisingToolsResource;
use Drupal\unicorn_advertising_tools\Support\AdvertisingToolsUtils;
use Drupal\unicorn_core\Resources\PaginatorResource;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Paginator;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AdvertisingToolsController extends ControllerBase {

  public function __construct(
    private readonly Connection $connection,
    private readonly ExtendedValidator $validator,
    private readonly AdvertisingToolsResource $resource,
    private readonly AdvertisingToolsRepository $advertisingToolsRepository,
    private readonly AdvertisingToolsUtils $advertisingToolsUtils,
    private readonly PaginatorResource $paginatorResource,
    private readonly AdvertisingToolsConfig $advertisingToolsConfig,
  ) {}

  public function index(Request $request): CacheableJsonResponse {
    $filters = AdvertisingToolsFilters::fromRequest($request);

    $repository = $this->advertisingToolsRepository->withNode();

    if ($filters->state !== null) {
      $repository->whereState($filters->state);
    }

    if ($filters->nodeTitle) {
      $repository->whereTitle($filters->nodeTitle);
    }

    $repository = match ($filters->sort) {
      'history_date' => $repository->orderByHistoryDate($filters->direction),
      'promoted_date' => $repository->orderByPromotedDate($filters->direction),
      default => $repository->orderById($filters->direction),
    };

    $result = $repository->paginate($filters->page, $filters->perPage);
    $items = $this->resource->toApiCollection($result['data']);

    $paginator = new Paginator(
      total: $result['total'],
      count: count($result['data']),
      page: $filters->page,
      perPage: $filters->perPage,
    );

    $response = ResponseBuilder::make([
      'data' => $items,
      'pager' => $this->paginatorResource->toApi($paginator),
    ])->cacheableJson();

    $cacheMetadata = new CacheableMetadata()
      ->addCacheTags([$this->advertisingToolsConfig->getPromotedDashboardTag()])
      ->addCacheContexts(['url.query_args']);

    $response->addCacheableDependency($cacheMetadata);
    return $response;
  }

  public function show(int $entityId): JsonResponse {
    $item = $this->advertisingToolsRepository
      ->withNode()
      ->whereEntityId($entityId)
      ->first();

    $response = $item ? $this->resource->toApi($item) : [];
    return ResponseBuilder::make($response)->json();
  }

  public function store(Request $request): JsonResponse {
    $dto = AdvertisingToolRecordDto::fromRequest($request);
    $this->validator->validateAndThrow($dto, groups: ['create']);

    $toStore = $dto->toStoreRecord();
    $toStore['promoted_date'] = new DateTimeImmutable('now', new DateTimeZone('UTC'))->getTimestamp();

    try {
      $this->connection->insert('unicorn_advertising_tools')
        ->fields($toStore)
        ->execute();
    }
    catch (Throwable $e) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Operation not performed.', $e);
    }

    $this->advertisingToolsUtils->clearAdToolsCache(TRUE);

    return ResponseBuilder::make(null, Response::HTTP_CREATED)->json();
  }

  public function update(Request $request, int $entityId): JsonResponse {
    $dto = AdvertisingToolRecordDto::fromPatchRequest($request);
    $this->validator->validateAndThrow($dto);

    $transaction = $this->connection->startTransaction();

    $fields = $dto->toUpdateRecord();
    if (!is_null($dto->state)) {
      $fields['promoted_date'] = $this->fillPromotedDate($entityId, $dto->state);
    }

    $result = $this->connection->update('unicorn_advertising_tools')
      ->fields($fields)
      ->condition('entity_id', $entityId)
      ->execute();

    if (!$result) {
      throw new BadRequestHttpException('No operation performed.');
    }

    unset($transaction);

    $this->advertisingToolsUtils->clearAdToolsCache($dto->state !== null);

    return ResponseBuilder::make()->json();
  }

  public function destroy(int $entityId): JsonResponse {

    $result = $this->connection->delete('unicorn_advertising_tools')
      ->condition('entity_id', $entityId)
      ->execute();

    if (!$result) {
      throw new BadRequestHttpException('No operation performed.');
    }

    $this->advertisingToolsUtils->clearAdToolsCache(TRUE);

    return ResponseBuilder::make()->json();
  }

  private function fillPromotedDate(int $entityId, bool $newState): int {
    $query = $this->connection
      ->select('unicorn_advertising_tools', 'uat')
      ->fields('uat', ['state', 'promoted_date'])
      ->condition('uat.entity_id', $entityId);
    $query->forUpdate();
    $row = $query->execute()?->fetchAssoc();

    if (!$row) {
      throw new RuntimeException('Item does not exist.');
    }

    $previousState = (bool) $row['state'];

    if ($previousState === FALSE && $newState === TRUE) {
      return new DateTimeImmutable('now', new DateTimeZone('UTC'))->getTimestamp();
    }

    return (int) $row['promoted_date'];
  }

}
