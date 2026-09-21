<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Enum;

use Drupal\node\NodeInterface;

/**
 * Article publishing states exposed to API consumers.
 */
enum ArticleStatus: int {

  case Draft = 0;
  case Published = 1;
  case Scheduled = 2;

  public static function fromArticle(NodeInterface $article): self {
    if ($article->isPublished()) {
      return self::Published;
    }

    if ($article->hasField('publish_on') && !$article->get('publish_on')->isEmpty()) {
      return self::Scheduled;
    }

    return self::Draft;
  }

  public function isDraft(): bool {
    return $this === self::Draft;
  }

  public function isPublished(): bool {
    return $this === self::Published;
  }

  public function isScheduled(): bool {
    return $this === self::Scheduled;
  }

}
