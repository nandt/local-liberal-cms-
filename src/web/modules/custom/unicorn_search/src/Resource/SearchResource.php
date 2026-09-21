<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Resource;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\unicorn_core\Resources\BaseResource;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_search\Configuration\SearchConfig;
use Drupal\unicorn_search\Support\ItemNodeIdResolver;

/**
 *
 * @phpstan-type SearchApiRecord array{
 *   id: int,
 *   title: string,
 *   subtitle: ?string,
 *   url: string,
 *   created: ?string,
 *   updated: ?string,
 *   category: ?string,
 *   author: ?string,
 *   image_url: ?string,
 *   eidos_arthrou: ?string,
 *   video_duration: ?string
 * }
 */
final class SearchResource extends BaseResource {

  public function __construct(
    DateFormatterInterface $dateFormatter,
    private readonly ItemNodeIdResolver $itemNodeIdResolver,
    private readonly SearchConfig $searchConfig,
  ) {
    parent::__construct($dateFormatter);
  }

  /**
   * @param array<int, ItemInterface<string, FieldInterface>> $items
   *
   * @return array<int, SearchApiRecord>
   */
  public function toApiCollection(array $items): array {
    return Collection::wrap($items)
      ->transform(fn(ItemInterface $item): array => $this->toApi($item), Collection::class)
      ->toArray();
  }

  /**
   * @param ItemInterface<string, FieldInterface> $item
   *
   * @return SearchApiRecord
   */
  private function toApi(ItemInterface $item): array {
    return [
      'id' => $this->itemNodeIdResolver->getNodeId($item),
      'title' => $this->getFieldValue($item, 'title') ?? '',
      'subtitle' => $this->getFieldValue($item, 'field_subtitle'),
      'url' => $this->getFieldValue($item, $this->searchConfig->getSearchApiUrlField()) ?? '',
      'created' => $this->getDateFieldValue($item, 'created'),
      'updated' => $this->getDateFieldValue($item, 'field_teleytaia_enimerosi'),
      'category' => $this->getFieldValue($item, 'field_category'),
      'author' => $this->getFieldValue($item, 'field_arthrografos'),
      'image_url' => $this->getFieldValue($item, $this->searchConfig->getSearchApiImageStyleUrlField()),
      'eidos_arthrou' => $this->getFieldValue($item, 'field_eidos_arthrou'),
      'video_duration' => $this->getFieldValue($item, 'field_vid_article_duration'),
    ];
  }

  /**
   * @param ItemInterface<string, FieldInterface> $item
   */
  private function getFieldValue(ItemInterface $item, string $fieldId): ?string {
    $field = $item->getField($fieldId, false);
    $value = $field?->getValues()[0] ?? null;

    return $value !== null ? (string) $value : null;
  }

  /**
   * @param ItemInterface<string, FieldInterface> $item
   */
  private function getDateFieldValue(ItemInterface $item, string $fieldId): ?string {
    $value = $this->getFieldValue($item, $fieldId);

    return $value !== null ? $this->getTimestampAsHtmlDatetime((int) $value) : null;
  }

}
