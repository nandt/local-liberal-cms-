<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Ad-server targeting data for AMP ad slots.
 *
 * Port of the deleted liberal_advertising_tools's LiberalAdTargeting.
 */
final readonly class AmpAdTargeting {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * @return array<string, mixed>
   */
  public function build(NodeInterface $node): array {
    $categories = $this->getCategories($node);
    $targeting = $this->formatCategories($categories);
    $targeting['ID'] = $node->id();

    return $targeting;
  }

  /**
   * @return array{tags?: list<string>, category?: string, parent?: string}
   */
  private function getCategories(NodeInterface $node): array {
    $categories = [];

    $tagIds = array_column($node->get('field_liberal_tags')->getValue(), 'target_id');
    $categoryIds = array_column($node->get('field_liberal_category')->getValue(), 'target_id');
    $ids = array_merge($tagIds, $categoryIds);

    if ($ids === []) {
      return $categories;
    }

    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $termStorage->loadMultiple($ids);

    // The category term is always last in $ids, matching the original
    // service's assumption.
    $categoryTerm = array_pop($terms);

    foreach ($terms as $term) {
      $categories['tags'][] = $term->getName();
    }

    if ($categoryTerm === NULL) {
      return $categories;
    }

    $categories['category'] = $categoryTerm->getName();
    $parentId = $categoryTerm->get('parent')->target_id;

    if (!empty($parentId)) {
      $parentTerm = $termStorage->load($parentId);
      if ($parentTerm !== NULL) {
        $categories['parent'] = $parentTerm->getName();
      }
    }

    return $categories;
  }

  /**
   * @param array{tags?: list<string>, category?: string, parent?: string} $categories
   *
   * @return array<string, mixed>
   */
  private function formatCategories(array $categories): array {
    $formatted = [];

    if (!empty($categories['tags'])) {
      $formatted['Tag'] = $categories['tags'];
    }

    if (!empty($categories['parent'])) {
      $formatted['Category'] = $categories['category'] ?? NULL;
    }

    $formatted['Rootcategory'] = $categories['parent'] ?? ($categories['category'] ?? NULL);

    return $formatted;
  }

}
