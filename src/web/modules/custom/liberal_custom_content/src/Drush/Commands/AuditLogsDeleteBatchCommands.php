<?php

namespace Drupal\liberal_custom_content\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;

/**
 * A Drush commandfile.
 */

class AuditLogsDeleteBatchCommands extends DrushCommands {

  const CHUNK = 3000;

  use StringTranslationTrait;

  /**
   * @return array
   */

  private function getOutdatedLogs($cleanupDate, $option = FALSE) {
    $rows = [];

    if (isset($option)) {
      switch ($option) {
        case 'relative_date':
          $request_time = \Drupal::time()->getRequestTime();
          $delete_from_time = strtotime((string) $cleanupDate, $request_time);
          break;

        case 'absolute_date':
          $cleanupDateTime = \DateTime::createFromFormat('d-m-Y', $cleanupDate, new \DateTimeZone("Europe/Athens"));
          if ($cleanupDateTime !== FALSE) {
            $delete_from_time = $cleanupDateTime->getTimestamp();
          }
          break;
      }
    }

    if (!empty($delete_from_time)) {
      $query = \Drupal::database()->select('admin_audit_trail', 'at');
      $rows = $query->fields('at', ['lid'])
        ->condition('created', $delete_from_time, '<')
        ->execute()
        ->fetchAllAssoc('lid', \Drupal\Core\Database\Statement\FetchAs::Associative);
    }

    return $rows;
  }

  /**
  * Maintenance / Cleanup of Audit logs
  */
  #[CLI\Command(name: 'cleanup:auditlogs', aliases: ['cl_auditlogs'])]
  #[CLI\Option(name: 'relative_date', description: 'Set the date (d-m-Y) to delete based on a relative date. All records before that date will be deleted, ex -1 week.')]
  #[CLI\Option(name: 'absolute_date', description: 'Set the date (d-m-Y) if you want to delete based on a set date, ex 20-03-2023. All records before that date will be deleted. The time used is the one the command runs')]
  #[CLI\Usage(name: 'drush cleanup:auditlogs --relative_date="-1 week"', description: 'Clean the log records in the period of 1 week ( starting from now and going back 1 week ).')]

  public function cleanupLogs($options = ['relative_date' => NULL, 'absolute_date' => NULL]) {

    if ($options['relative_date']) {
      $cleanupDate = $options['relative_date'];
      $option = "relative_date";
    }
    elseif ($options['absolute_date']) {
      $cleanupDate = $options['absolute_date'];
      $option = "absolute_date";
    }
    else {
      $cleanupDate = FALSE;
      $option = NULL;
    }

    // get all articles flagged in news
    $logs = $this->getOutdatedLogs($cleanupDate, $option);

    $chunks = array_chunk($logs, self::CHUNK);

    $batchBuilder = new BatchBuilder();
    $numOperations = 0;
    $batchId = 1;

    foreach ($chunks as $chunk) {
      $batchBuilder->addOperation([self::class, 'processLogs'], [
        $batchId,
        $chunk,
      ]);

      $batchId++;
      $numOperations++;
    }

    if (!empty($chunks)) {
      $batchBuilder
        ->setTitle($this->t('Audit Logs Cleanup Operation'))
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
 *   Array of lids to process in batch
 * @param object $context
 *   Context for operations.
 */

  // Always make AddOperation and FinishedCallback public static
  public static function processLogs($id, $chunk, &$context) {
    $numbers = array_column($chunk, 'lid');
    $min = min($numbers);
    $max = max($numbers);

    $query = \Drupal::database()->delete('admin_audit_trail');
    $query->condition('lid', [$min, $max], 'BETWEEN');
    $query->execute();
  }

}
