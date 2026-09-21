<?php

namespace Drupal\liberal_sitemap_generator\Services;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Database\Connection;

class NewsSitemapLinks {

  /**
   * The database connection used.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  protected function getNewsQueryRange() {
    // get date now
    $datetime = new DrupalDateTime();
    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

    // set timezone to storage timezone. Because otherwise site timezone is used
    $datetime->setTimezone($timezone);

    // get the start date which is now formatted for query
    $date_start = $datetime->getTimestamp();

    $interval = \DateInterval::createFromDateString('-48 hours');
    $datetime_end = $datetime->add($interval);

    // get the end date which is formatted for query
    $date_end = $datetime_end->getTimestamp();

    return [$date_start, $date_end];
  }

  public function getNewsSitemapLinksQuery() {
    $date = $this->getNewsQueryRange();
    $query = $this->connection->select('node_field_data', 'nfd')
      ->condition('status', 1, '=')
      ->condition('type', 'article_liberal')
      ->condition('created', $date[1], '>=')
      ->condition('created', $date[0], '<=')
      ->fields('nfd', ['nid'])
      ->orderBy('nid', 'ASC');
    $query = $query->execute();

    $results = $query->fetchCol();
    return $results;
  }

}
