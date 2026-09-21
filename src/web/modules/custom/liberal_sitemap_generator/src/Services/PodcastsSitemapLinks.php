<?php

namespace Drupal\liberal_sitemap_generator\Services;

class PodcastsSitemapLinks {

  public function getPodcastsSitemapLinks($date_sitemap = [], $options = [], $count = FALSE): array {

    if (empty($options['date_option'])) {
      throw new \InvalidArgumentException(
      'sitemap:generate podcasts requires --date_option (supported: year-current)'
      );
    }

    $combined_results = [];
    $connection = \Drupal::database();

    /*
     *  use the simplest form of query to improve performance as much as possible and also do
     *  the sorting in php
     */

    // General query for all uses
    $query = $connection->select('node_field_data', 'nfd')
      ->condition('status', 1, '=')
      ->condition('type', 'podcast')
      ->fields('nfd', ['nid', 'created'])
      ->orderBy('nid', 'ASC');

    // Modify query according to options. Only run if the dates array is not yet calculated
    switch ($options['date_option']) {
      case 'year-current':
        $min_date = $this->getBoYear(\Drupal::time()->getRequestTime());
        $max_date = $this->getEoYear(\Drupal::time()->getRequestTime());
        $date_sitemap = $this->fillSitemapPodcast($min_date, $max_date);
        break;
    }

    $query->condition('created', $date_sitemap['timestamp']['min_date'], '>=')
      ->condition('created', $date_sitemap['timestamp']['max_date'], '<=');

    // if it's a count query, rewrite the whole query before execution
    if ($count) {
      $query = $connection->select('node_field_data', 'nfd');
      $query->condition('status', 1, '=')
        ->condition('type', 'podcast')
        ->condition('created', $date_sitemap['timestamp']['min_date'], '>=')
        ->condition('created', $date_sitemap['timestamp']['max_date'], '<=')
        ->fields('nfd', ['nid', 'created']);
    }

    if ($count) {
      $results = $query->countQuery()->execute()->fetchField();
    }
    else {
      $results = $query->execute()->fetchAllAssoc('nid', \Drupal\Core\Database\Statement\FetchAs::Associative);
    }

    $combined_results = [
      'results' => $results,
      'dates_sitemap' => $date_sitemap,
      'current_date' => $date_sitemap,
    ];

    return $combined_results;
  }

  /**
* @param DateTime $min_date
* The start of the day
* @param Datetime $max_date
* The end of the day
* @param Datetime $original_date
* The original date inputted from the script to get the filename fragment
* @return array
*/
  private function fillSitemapPodcast($min_date, $max_date) {
    return [
      'readable' => [
        'min_date' => $min_date->format('d-m-Y H:i:s'),
        'max_date' => $max_date->format('d-m-Y H:i:s'),
      ],
      'timestamp' => [
        'min_date' => $min_date->getTimestamp(),
        'max_date' => $max_date->getTimestamp(),
      ],
      'filename_fragment' => $min_date->format('Y'),
    ];
  }

  public function getBoDay($timestamp) {
    $time = new \DateTime();
    $time->setTimezone(new \DateTimeZone('Europe/Athens'));
    $time->setTimestamp($timestamp);
    $time->modify('today');

    return $time;
  }

  public function getEoDay($timestamp) {
    $time = new \DateTime();
    $time->setTimezone(new \DateTimeZone('Europe/Athens'));
    $time->setTimestamp($timestamp);

    $time->modify('tomorrow');

    /** Adjust from the start of next day to the end of the day,
    * Decremented the second as a long timestamp rather than the
    * DateTime object, due to oddities around modifying
    * into skipped hours of day-lights-saving.
    */

    $endOfDateTimestamp = $time->getTimestamp();
    $time->setTimestamp($endOfDateTimestamp - 1);

    return $time;
  }

  public function getBoYear($timestamp) {
    $time = new \DateTime();
    $time->setTimezone(new \DateTimeZone('Europe/Athens'));
    $time->setTimestamp($timestamp);
    //First day of month, first minute, second etc
    $time->modify('first day of this month')->modify('first day of January')->setTime(0, 0, 0);
    return $time;
  }

  public function getEoYear($timestamp) {
    //Last day of month
    $time = new \DateTime();
    $time->setTimezone(new \DateTimeZone('Europe/Athens'));
    $time->setTimestamp($timestamp);

    $time->modify('last day of December')->setTime(0, 0, 0);
    return $this->getEoDay($time->getTimestamp());
  }

}
