<?php

declare(strict_types=1);

namespace Drupal\liberal_stocks_app\Hooks;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * @phpstan-consistent-constructor
 */
class LiberalStocksAppHooks {

  public function __construct(
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    $this->invalidateMetohesTags($node);
  }

  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    $this->invalidateMetohesTags($node, 'delete');
  }

  private function invalidateMetohesTags(
    NodeInterface $node,
    string $operation = 'edit',
  ): void {
    // Act when there is a value in field_metohi, or there was a value previously.
    if (
      $node->bundle() === 'article_liberal'
      && (
        !empty($node->get('field_metohi')->getValue())
        || $node->getOriginal() !== NULL
        && !empty($node->getOriginal()->get('field_metohi')->getValue())
      )
    ) {
      $clear_performed = FALSE;

      /**
       * We can't only look for field_metohi changes. So if a node with any
       * metohi changes for published nodes, for any reason, we need to clear
       * the cache.
       */
      if ($node->isPublished()) {
          $this->cacheTagsInvalidator->invalidateTags(['liberalmetohes']);
          $clear_performed = TRUE;
      }

      // If publish status changed, clear cache to include or remove.
      if (
        $node->getOriginal() !== NULL
        && $node->getOriginal()->isPublished() != $node->isPublished()
      ) {
        if (!$clear_performed) {
          $this->cacheTagsInvalidator->invalidateTags(['liberalmetohes']);
        }
      }
    }
  }

}
