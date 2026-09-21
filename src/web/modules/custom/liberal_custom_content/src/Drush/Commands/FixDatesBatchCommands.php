<?php

namespace Drupal\liberal_custom_content\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drush\Attributes as CLI;

/**
 * A Drush commandfile.
 */

class FixDatesBatchCommands extends DrushCommands {

  const LIMIT = 20;
  const CHUNK = 40;

  use StringTranslationTrait;

  /**
   * @return array
   */

  private function getFixDateNodes() {
    $fix_dates = \Drupal::entityQuery('node')
      ->condition('type', 'article_liberal')
      ->condition('field_teleytaia_enimerosi', '%T%', 'NOT LIKE')
      ->accessCheck(FALSE)
      ->execute();

    return $fix_dates;
  }

  /**
  * Fix Node Dates in DB
  */
  #[CLI\Command(name: 'fix:db_dates')]

  public function fixDbDates() {

    // get all articles with wrong dates
    $fix_dates = $this->getFixDateNodes();

    $chunks = array_chunk($fix_dates, self::CHUNK);

    $batchBuilder = new BatchBuilder();
    $total_nodes = count($fix_dates);

    foreach ($chunks as $chunk) {
      $batchBuilder->addOperation([self::class, 'processDates'], [
        $total_nodes,
        $chunk,
      ]);
    }

    if (!empty($chunks)) {
      $batchBuilder
        ->setTitle($this->t('Fix dates in db Operation'))
        ->setFinishCallback([self::class, 'fixDatesFinished'])
        ->setErrorMessage(t('Batch has encountered an error'));

      batch_set($batchBuilder->toArray());
      drush_backend_batch_process();
    }
  }

  /**
 * Batch process callback.
 *
 * @param int $total_nodes
 *   the total number of nodes to be processed.
 * @param array $chunk
 *   Array of nids to process in batch
 * @param object $context
 *   Context for operations.
 */

  // Always make AddOperation and FinishedCallback public static
  public static function processDates($total_nodes, $chunk, &$context) {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

    // initiate counter of processed
    if (!isset($context['results']['count_total'])) {
      $context['results']['count_total'] = 0;
    }

    // initiate total
    if (!isset($context['results']['total_nodes'])) {
      $context['results']['total_nodes'] = $total_nodes;
    }

    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        if (!empty($node->field_teleytaia_enimerosi->value)) {
          $datetime = new DrupalDateTime($node->field_teleytaia_enimerosi->value);
          $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

          // set timezone to storage timezone. Because otherwise site timezone is used
          $datetime->setTimezone($timezone);

          // fixed date for drupal format
          $date_fixed = $datetime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
          $node->set('field_teleytaia_enimerosi', $date_fixed);

          // bonus also set full_html to nodes that don't have a format
          $body = $node->get('body')->value;
          $format = $node->get('body')->format;

          // only run when body is not empty
          if (!empty($body)) {
            // set to full_html
            $format = 'full_html';

            // set the new format
            $node->body->format = $format;
          }

          // increment the processed counter
          $context['results']['count_total']++;

          $node->save();
        }
      }
    }
  }

  /**
   * @param array<string, mixed> $results
   */
  public static function fixDatesFinished(bool $success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()
        ->addStatus(t('All nodes processed succesfully.'));
    }
    else {
      \Drupal::messenger()->addError('There was an error during the operation');
    }

    \Drupal::messenger()
      ->addMessage(t('Processed @number nodes out of @total_nodes',
        ['@number' => $results['count_total'], '@total_nodes' => $results['total_nodes']]));
  }

}
