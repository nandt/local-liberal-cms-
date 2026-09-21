<?php

declare(strict_types=1);

namespace Drupal\unicorn_sources\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

final readonly class SourceUsageChecker {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function isUsedByPublishedArticle(TermInterface $source): bool {
    if ($source->bundle() !== 'piges_arthron' || $source->isNew()) {
      return FALSE;
    }

    $articleIds = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_pigi_arthroy', $source->id())
      ->range(0, 1)
      ->execute();

    return $articleIds !== [];
  }

}
