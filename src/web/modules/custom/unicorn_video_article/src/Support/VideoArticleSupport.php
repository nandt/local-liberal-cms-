<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Support;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_video_article\Configuration\VideoArticleConfig;

final readonly class VideoArticleSupport {


  public function __construct(
    private VideoArticleConfig $videoArticleConfig,
  ) {}

  public function isVideoArticle(NodeInterface $node): bool {
    if ($node->bundle() !== $this->videoArticleConfig->getBundle()) {
      return FALSE;
    }

    $termCollection = Collection::wrap($node->get('field_eidos_arthrou')->referencedEntities());

    return $termCollection->contains(
        fn ($term): bool =>
        $term instanceof TermInterface
        && in_array($term->getName(), $this->videoArticleConfig->getTermNames(), TRUE),
    );
  }

  public function extractVideoId(string $sourceCode): ?string {
    if (!preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $sourceCode, $matches)) {
      return NULL;
    }

    $path = parse_url($matches[1], PHP_URL_PATH) ?: '';
    return explode('/', $path)[1] ?? NULL;
  }

}
