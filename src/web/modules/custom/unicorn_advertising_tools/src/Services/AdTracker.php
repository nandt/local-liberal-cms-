<?php

namespace Drupal\unicorn_advertising_tools\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Component\Datetime\TimeInterface;

readonly class AdTracker {

  public function __construct(
    private Connection $connection,
    private QueueFactory $queue,
    private TimeInterface $time,
  ) {
  }

  public function countImpressions(int $id): ?int {
    return $this->connection
      ->update('unicorn_advertising_tools')
      ->expression('impressions', 'impressions + 1')
      ->condition('entity_id', $id)
      ->execute();
  }

  /**
   * @param array<int, int> $data
   */
  public function massCountImpressions(array $data): void {
    foreach($data as $nid => $node_impressions) {
     $this->connection
      ->update('unicorn_advertising_tools')
      ->expression('impressions', 'impressions + :impressions', [':impressions' => $node_impressions])
      ->condition('entity_id', $nid)
      ->execute();
    }
  }

  /**
   * @param array<int, int> $data
   */
  public function massRecordViews(array $data): void {
    foreach($data as $nid => $node_views) {
      $this->connection
        ->merge('node_counter')
        ->key('nid', $nid)
        ->fields([
          'daycount' => $node_views,
          'totalcount' => $node_views,
          'timestamp' => $this->time->getRequestTime(),
        ])
        ->expression('daycount', '[daycount] + :node_views', [':node_views' => $node_views])
        ->expression('totalcount', '[totalcount] + :node_views', [':node_views' => $node_views])
        ->execute();
    }
  }

  public function addToQueueImpressions(int $id): void {
    $queue = $this->queue->get('ad_tracker_queue');

    $queue->createItem(['nid' => $id]);
  }

  public function addToQueueStatistics(int $id): void {
      $queue = $this->queue->get('unicorn_statistics_queue');

      $queue->createItem(['nid' => $id]);
    }

  public function countClicks(int $id): ?int {
    return $this->connection
      ->update('unicorn_advertising_tools')
      ->expression('clicks', 'clicks + 1')
      ->condition('entity_id', $id)
      ->execute();
  }
}
