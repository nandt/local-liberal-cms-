<?php

namespace Drupal\liberal_sitemap_generator\Services;

class IndexSitemapLinks {

  public function getIndexSitemapLinks($chunk, $limit) {

    /*
     *  use the simplest form of query to improve performance as much as possible and also do
     *  the sorting in php
     */

    $connection = \Drupal::database();
    $days_sitemaps = [];

    $query = $connection->select('file_managed', 'fm');
    $query->condition('filemime', 'application/xml', '=')
      ->condition('uri', 'public://%', 'LIKE')
      ->condition(
      $query->orConditionGroup()
        ->condition('filename', 'sitemap_ar_%', 'LIKE')
        ->condition('filename', 'sitemap_video_%', 'LIKE')
    )
      ->fields('fm', ['fid', 'filename', 'created'])
      ->orderBy('fid', 'ASC');

    $fids = $query->execute()->fetchAllAssoc('fid', \Drupal\Core\Database\Statement\FetchAs::Associative);

    $config = \Drupal::config('liberal_sitemap_generator.settings');
    $daily_housekeeping = $config->get('daily_sitemaps_housekeeping');

    // execute this part only if the daily option is enabled
    if (!empty($daily_housekeeping)) {
      foreach ($fids as $fid) {
        if (str_starts_with((string) $fid['filename'], 'sitemap_ar_d_')) {
          $key_date = str_replace('sitemap_ar_d_', '', $fid['filename']);
          $key_date = str_replace('.xml', '', $key_date);
          $days_sitemaps[$fid['fid']] = [
            'fid' => $fid['fid'],
            'created' => $fid['created'],
            'key_date' => $key_date,
          ];
        }
      }

      $timestamp = \Drupal::time()->getCurrentTime();
      $this->MaintainDailySitemapFiles($timestamp, $days_sitemaps);
    }

    $fids = array_column($fids, 'fid');
    return $fids;

  }

  /**
   * Daily sitemap files maintenance
   *
   * @param int $execution_time
   *  Timestamp of the "now" time
   */
  private function MaintainDailySitemapFiles($execution_time, $day_sitemaps) {
    $count_months = [];

    // any day will do. We just need to check the month difference.
    $execution_date = \DateTime::createFromFormat('U', $execution_time);
    $execution_date->setTimezone(new \DateTimeZone("Europe/Athens"));

    foreach ($day_sitemaps as $day_sitemap) {
      $key_date = date("d-m-Y", strtotime((string) $day_sitemap['key_date']));
      $day_sitemaps_time = \Drupal::service('liberal_sitemap_generator.articles_sitemap_links')->FixInputDate($key_date)->getTimestamp();
      $day_sitemaps_date = \Drupal::service('liberal_sitemap_generator.articles_sitemap_links')->getBoMonth($day_sitemaps_time);
      $count_months = \Drupal::service('liberal_sitemap_generator.articles_sitemap_links')->getMonthsDifference($execution_date, $day_sitemaps_date);

      if ($count_months > 0) {
        $delete[] = $day_sitemap['fid'];
      }
    }

    if (!empty($delete)) {

      $file_storage = \Drupal::entityTypeManager()->getStorage('file');
      $files = $file_storage->loadMultiple($delete);

      // Maybe also delete one by one with loop
      $file_storage->delete($files);
    }
  }

}
