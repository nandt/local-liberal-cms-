<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Configuration;

use Drupal\unicorn_core\Support\Collection;

class AdvertisingToolsConfig {

  private const string CACHE_TAG_PROMOTED_DASHBOARD = 'ad_tools_cache';
  private const string CACHE_TAG_PROMOTED_LIST = 'ad_tools_cache_list';
  private const string PROMOTED_ARTICLES_CACHE_CID = 'ad_tools_promoted_articles_cache';
  private const int SECOND_SLOT_THRESHOLD = 4;

  private readonly ?string $ampPublisherId;
  private readonly int $queueProcessLimit;
  private readonly int $promotedArticlePosition;
  private readonly int $secondPromotedArticlePosition;

  public function __construct() {
    $this->queueProcessLimit = (int) getenv('REDIS_QUEUE_PROCESS_LIMIT');
    $this->ampPublisherId = (string) getenv('AMP_PUBLISHER_ID') ?: null;
    $this->promotedArticlePosition = 1;
    $this->secondPromotedArticlePosition = 4;
  }

  public function getPromotedDashboardTag(): string {
    return self::CACHE_TAG_PROMOTED_DASHBOARD;
  }

  public function getPromotedListTag(): string {
    return self::CACHE_TAG_PROMOTED_LIST;
  }

  public function getQueueProcessLimit(): int {
    return $this->queueProcessLimit ?: 50;
  }

  public function getPromotedArticlesCacheId(): string {
    return self::PROMOTED_ARTICLES_CACHE_CID;
  }

  public function getAmpPublisherId(): ?string {
    return $this->ampPublisherId;
  }

  public function getPromotedArticleCount(int $count): int {
    return $count >= self::SECOND_SLOT_THRESHOLD ? 2 : 1;
  }

  /**
   * @return Collection<int, int>
   */
  public function getPromotedArticlePositions(): Collection {
    return Collection::wrap([
      $this->promotedArticlePosition,
      $this->secondPromotedArticlePosition,
    ]);
  }

}
