<?php

/* This is a one-shot command to migrate field_teleytaia_enimerosi data */

namespace Drupal\liberal_date_fields\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Messenger\MessengerTrait;
use Drush\Attributes as CLI;

/**
 * A Drush commandfile.
 */

class MigrateTeleytaiaEnimerosiCommands extends DrushCommands {

  const CHUNK = 40;

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * @return array
   */

  private function getNodeIds() {
    $nodes = [];

    $connection = \Drupal::database();
    $query = $connection->select('node_field_data', 'nfd');
    $query->innerJoin('node__field_teleytaia_enimerosi', 'nfte', 'nfd.nid = nfte.entity_id');

    $query
      ->fields('nfd', ['nid']);
    $query->condition('nfd.status', 1)
      ->condition('nfd.type', "article_liberal")
      ->orderBy('nid', 'ASC');

    $nodes = $query->execute()->fetchCol();
    return $nodes;
  }

  /**
  * Migrate Teleytaia Enimerosi
  */
  #[CLI\Command(name: 'teleytaia_enimerosi')]

  public function migrateTeleytaiaEnimerosiNodes() {

    $nodes = $this->getNodeIds();

    $chunks = array_chunk($nodes, self::CHUNK);

    $batchBuilder = new BatchBuilder();
    $total_nodes = count($nodes);

    foreach ($chunks as $chunk) {
      $batchBuilder->addOperation([MigrateTeleytaiaEnimerosiCommands::class, 'processNodes'], [
        $total_nodes,
        $chunk,
      ]);
    }

    if (!empty($chunks)) {
      $batchBuilder
        ->setTitle($this->t('Migrate Teleytaia Enimerosi Operation'))
        ->setFinishCallback([MigrateTeleytaiaEnimerosiCommands::class, 'migrateTeleytaiaEnimerosiFinished'])
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
  public static function processNodes($total_nodes, $chunk, &$context) {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

    // initiate counter of total processed nodes
    if (!isset($context['results']['count_total'])) {
      $context['results']['count_total'] = 0;
    }

    // initiate counter of processed nodes
    if (!isset($context['results']['processed_total'])) {
      $context['results']['processed_total'] = 0;
    }

    // initiate total
    if (!isset($context['results']['total_nodes'])) {
      $context['results']['total_nodes'] = $total_nodes;
    }

    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        // if the created date is different than teleutaia_enimerosi date then update it
        $created = $node->getCreatedTime();
        $teleytaia_enimerosi = $node->get('field_teleytaia_enimerosi')->value;

        // Check value is not empty before acting
        if (!empty($teleytaia_enimerosi)) {
          // get UTC default timezone dates are stored (its UTC 0)
          $utc_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
          $teleytaia_enimerosi_datetime = new \DateTime($teleytaia_enimerosi, $utc_timezone);
          $teleytaia_enimerosi_timestamp = $teleytaia_enimerosi_datetime->getTimestamp();

          if ($created != $teleytaia_enimerosi_timestamp) {
            $node->setCreatedTime($teleytaia_enimerosi_timestamp);
          }

          // Set the date to blank anyway, even if no syncing is needed
          $node->get('field_teleytaia_enimerosi')->removeItem(0);
          $node->save();
          $context['results']['processed_total']++;
        }
      }
    }

    $context['results']['count_total'] += count($nodes);
  }

  /**
   * @param array<string, mixed> $results
   */
  public static function migrateTeleytaiaEnimerosiFinished(bool $success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()
        ->addMessage(new TranslatableMarkup('All nodes processed succesfully. Processed @number nodes out of @total_nodes. Changed @changed',
      ['@number' => $results['count_total'], '@total_nodes' => $results['total_nodes'], '@changed' => $results['processed_total']]));
    }
    else {
      \Drupal::messenger()
        ->addError(new TranslatableMarkup('There was an error during the operation'));
    }
  }

}
