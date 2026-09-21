<?php

declare(strict_types=1);

namespace Drupal\unicorn_series\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\unicorn_series\Service\SeriesUsageChecker;

final readonly class SeriesHooks {

  public function __construct(
    private SeriesUsageChecker $seriesUsageChecker,
  ) {}

  /**
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entityType): void {
    if ($entityType->id() !== 'taxonomy_term') {
      return;
    }

    if (isset($fields['name'])) {
      $fields['name']->addConstraint('UniqueSeriesName');
    }
  }

  #[Hook('taxonomy_term_access')]
  public function taxonomyTermAccess(TermInterface $term, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'delete' || $term->bundle() !== 'series') {
      return AccessResult::neutral();
    }

    return AccessResult::forbiddenIf($this->seriesUsageChecker->isUsedByPublishedVideoArticle($term))
      ->addCacheableDependency($term)
      ->addCacheTags(['node_list']);
  }

}
