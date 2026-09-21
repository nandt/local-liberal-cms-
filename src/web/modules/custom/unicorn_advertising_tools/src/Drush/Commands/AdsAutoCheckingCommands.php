<?php

namespace Drupal\unicorn_advertising_tools\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Services\AdTracker;
use Drupal\unicorn_advertising_tools\Support\AdvertisingToolsUtils;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;

class AdsAutoCheckingCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly AdTracker $adTracker,
    private readonly Connection $connection,
    private readonly AdvertisingToolsUtils $advertisingToolsUtils,
    private readonly AdvertisingToolsConfig $advertisingToolsConfig,
    private readonly QueueFactory $queueFactory,
    private readonly TimeInterface $time,
  ) {
    parent::__construct();
  }

  /**
  * Disable promoted articles based on criteria
  */
  #[CLI\Command(name: 'advertisingtools:checkadscriteria', aliases: ['cads'])]
  public function checkAdsCriteria(): void {
    $results = $this->advertisingToolsUtils->queryPromotedArticles();
    $disabled_ads = FALSE;
    $request_time = $this->time->getRequestTime();

    // check all the array and if any criteria is fulfilled change state to 0
    foreach($results as $result) {
      if($request_time >= $result['end_date'] || $result['impressions'] >= $result['target_impressions']) {
        $query = $this->connection->update('unicorn_advertising_tools')
                ->fields(['state' => 0, 'history_date' => $request_time ])
                ->condition('entity_id', $result['entity_id']);

        $query->execute();
        $disabled_ads = TRUE;
      }
    }

    // clear the endpoints cache since changes took place
    if($disabled_ads) {
      $this->advertisingToolsUtils->clearAdToolsCache(TRUE);
    }
  }

  /**
   * Consumes the adtracker queue
   */
  #[CLI\Command(name: 'advertisingtools:consume_adtracker')]
  public function consumeAdTrackerQueue(): void {
    $queue = $this->queueFactory->get('ad_tracker_queue');
    $iterations = 0;
    $aggregated_data = [];

    /// As of now there is no way to claim all the items at once
    while (is_object($item = $queue->claimItem())) {
      /** @var object{data: array{nid: int}} $item */

      // Initialize or increment the count for each nid
      if (isset($aggregated_data[$item->data['nid']])) {
        $aggregated_data[$item->data['nid']]++;
      } else {
        $aggregated_data[$item->data['nid']] = 1;
      }

      $queue->deleteItem($item);
      $iterations++;

      // if we exceed the hard limit we set, break out of the loop
      if($iterations >= $this->advertisingToolsConfig->getQueueProcessLimit()) {
        break;
      }
    }

    $this->adTracker->massCountImpressions($aggregated_data);

    $this->advertisingToolsUtils->clearAdToolsCache();
  }

  /**
   * Consumes the statistics queue
   */
  #[CLI\Command(name: 'advertisingtools:consume_statistics')]
  public function consumeStatisticsQueue(): void {
    $queue = $this->queueFactory->get('unicorn_statistics_queue');
    $iterations = 0;
    $aggregated_data = [];

    /// As of now there is no way to claim all the items at once
    while (is_object($item = $queue->claimItem())) {
      /** @var object{data: array{nid: int}} $item */

      // Initialize or increment the count for each nid
      if (isset($aggregated_data[$item->data['nid']])) {
        $aggregated_data[$item->data['nid']]++;
      } else {
        $aggregated_data[$item->data['nid']] = 1;
      }

      $queue->deleteItem($item);
      $iterations++;

      // if we exceed the hard limit we set, break out of the loop
      if($iterations >= $this->advertisingToolsConfig->getQueueProcessLimit()) {
        break;
      }
    }

    $this->adTracker->massRecordViews($aggregated_data);
  }
}
