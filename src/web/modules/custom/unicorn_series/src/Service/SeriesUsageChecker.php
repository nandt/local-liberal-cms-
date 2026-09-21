<?php

declare(strict_types=1);

namespace Drupal\unicorn_series\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

final readonly class SeriesUsageChecker {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function isUsedByPublishedVideoArticle(TermInterface $series): bool {
    if ($series->bundle() !== 'series' || $series->isNew()) {
      return FALSE;
    }

    $articleIds = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_vid_article_series', $series->id())
      ->range(0, 1)
      ->execute();

    return $articleIds !== [];
  }

}
