<?php

namespace Drupal\liberal_custom_content\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;

/**
 * A Drush commandfile.
 */

class NewsBatchCommands extends DrushCommands {

  const LIMIT = 20;
  const CHUNK = 40;

  use StringTranslationTrait;

  /**
   * @return array
   */

  private function getCurrentNewsCleanup() {
    $current_news = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('status', '1' . '=')
      ->condition('type', ['article_liberal', 'podcast'], 'IN')
      ->condition('field_block_flag', ROH_EIDISEON, '=')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    return $current_news;
  }

  /**
  * Clears the flag of the news marked as "Feed"
  */
  #[CLI\Command(name: 'cleanup:news', aliases: ['cl_news'])]
  public function cleanupNews() {

    // get all articles flagged in news
    $current_news = $this->getCurrentNewsCleanup();

    // keep only first 20 then remove the rest
    $nids = array_slice($current_news, self::LIMIT, count($current_news), TRUE);

    $chunks = array_chunk($nids, self::CHUNK);

    $batchBuilder = new BatchBuilder();
    $numOperations = 0;
    $batchId = 1;

    foreach ($chunks as $chunk) {
      $batchBuilder->addOperation([self::class, 'processNews'], [
        $batchId,
        $chunk,
      ]);

      $batchId++;
      $numOperations++;
    }

    if (!empty($chunks)) {
      $batchBuilder
        ->setTitle($this->t('News Feed Cleanup Operation'))
        ->setErrorMessage(t('Batch has encountered an error'));

      batch_set($batchBuilder->toArray());
      drush_backend_batch_process();
    }
  }

  /**
 * Batch process callback.
 *
 * @param int $id
 *   Id of the batch.
 * @param array $chunk
 *   Array of nids to process in batch
 * @param object $context
 *   Context for operations.
 */

  // Always make AddOperation and FinishedCallback public static
  public static function processNews($id, $chunk, &$context) {

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        $block_flags = $node->get('field_block_flag');

        for ($i = 0; $i < $block_flags->count(); $i++) {
          $block_flag = $block_flags->get($i)->getValue();

          if ($block_flag['value'] == ROH_EIDISEON) {
            $block_flags->removeItem($i);
            // Decrement the counter to adjust for removed item.
            $i--;
          }
        }

        $node->save();
      }
    }
  }

}
