<?php

namespace Drupal\liberal_sitemap_generator\Services;

class ArticlesSitemapLinks {

  public function getArticlesSitemapLinks($dates_sitemap = [], $options = [], $limit = 0, $count = FALSE): array {

    if (empty($options['date_option'])) {
      throw new \InvalidArgumentException(
      'sitemap:generate articles requires --date_option ' .
      '(supported: today, day, month, month-current, month-previous, month-to-now, all)'
      );
    }

    $combined_results = [];
    $exclude_date_option = ['today', 'all', 'month-previous', 'month-current'];
    $connection = \Drupal::database();

    /*
     *  use the simplest form of query to improve performance as much as possible and also do
     *  the sorting in php
     */

    /*
     * General query for all uses.
     *
     * Οι στήλες γράφονται με το alias τους: το file_managed του join παρακάτω
     * έχει επίσης status και created, και χωρίς προσδιορισμό η MySQL σκάει με
     * "Column is ambiguous".
     */
    $query = $connection->select('node_field_data', 'nfd')
      ->condition('nfd.status', 1, '=')
      ->condition('nfd.type', 'article_liberal')
      ->fields('nfd', ['nid', 'created'])
      ->orderBy('nfd.nid', 'ASC');

    /*
     * Δύο left joins ώστε τα rows να κουβαλούν ό,τι χρειάζεται το sitemap χωρίς
     * να φορτώνουμε node entities — μιλάμε για 611.000 άρθρα. Left, όχι inner:
     * άρθρο χωρίς ημερομηνία ενημέρωσης ή χωρίς φωτογραφία πρέπει να μένει στο
     * sitemap, απλώς με λιγότερα στοιχεία.
     *
     * Το delta = 0 κρατά ένα row ανά άρθρο· χωρίς αυτό το fetchAllAssoc("nid")
     * θα κρατούσε σιωπηλά το τελευταίο διπλότυπο.
     */
    $query->leftJoin('node__field_teleytaia_enimerosi', 'te',
      'te.entity_id = nfd.nid AND te.deleted = 0 AND te.delta = 0 AND te.langcode = nfd.langcode');
    $query->addField('te', 'field_teleytaia_enimerosi_value', 'last_updated');

    $query->leftJoin('node__field_kentriki_fotografia', 'kf',
      'kf.entity_id = nfd.nid AND kf.deleted = 0 AND kf.delta = 0 AND kf.langcode = nfd.langcode');
    $query->addField('kf', 'field_kentriki_fotografia_alt', 'image_alt');
    $query->addField('kf', 'field_kentriki_fotografia_title', 'image_title');

    // Το uri απευθείας από το file_managed: το File::load ανά άρθρο θα έκανε
    // εκατοντάδες χιλιάδες entity loads για το ίδιο αποτέλεσμα.
    $query->leftJoin('file_managed', 'fm', 'fm.fid = kf.field_kentriki_fotografia_target_id');
    $query->addField('fm', 'uri', 'image_uri');

    // Modify query according to options. Only run if the dates array is not yet calculated
    if (empty($dates_sitemap)) {

      if (!in_array($options['date_option'], $exclude_date_option)) {
        $timestamp = $this->FixInputDate($options['date'])->getTimestamp();
      }

      switch ($options['date_option']) {
        case 'today':
          // regardless of input, get the time of the current day
          $timestamp = \Drupal::time()->getCurrentTime();

          $min_date = $this->getBoDay($timestamp);
          $max_date = $this->getEoDay($timestamp);

          $dates_sitemap = $this->fillSitemapDay($min_date, $max_date);
          break;

        case 'day':
          $min_date = $this->getBoDay($timestamp);
          $max_date = $this->getEoDay($timestamp);

          $dates_sitemap = $this->fillSitemapDay($min_date, $max_date);
          break;

        case 'month':
        case 'month-previous':
        case 'month-current':
          /* month-previous is meant to run once per month at the start to delete
          old daily files and create the monthly file */

          if ($options['date_option'] == 'month-current') {
            $min_date = $this->getBoMonth(\Drupal::time()->getRequestTime());
          }

          if ($options['date_option'] == 'month') {
            $min_date = $this->getBoMonth($timestamp);
          }

          if ($options['date_option'] == 'month-previous') {
            $min_date = new \DateTime("first day of last month", new \DateTimeZone("Europe/Athens"));
            $min_date = $this->getBoDay($min_date->getTimestamp());
          }

          $dates_sitemap = $this->fillSitemapMonths(0, $min_date);
          break;

        case 'month-to-now':
          $min_date = $this->getBoMonth($timestamp);
          $now_date = $this->getBoMonth(\Drupal::time()->getRequestTime());

          // find how many months difference to reach current date
          $count_months = $this->getMonthsDifference($min_date, $now_date);
          $dates_sitemap = $this->fillSitemapMonths($count_months, $min_date, $options['exclude_current_month']);
          break;

        case 'all':
          $min_query = $connection->select('node_field_data', 'nfd');
          $min_query->addExpression('MIN(created)', 'created');
          $min_query->condition('status', 1)
            ->fields('nfd', ['nid', 'created']);

          $min_results = $min_query->execute()->fetchAssoc('nid', \PDO::FETCH_ASSOC);
          $timestamp = $min_results['created'];

          // the date of the first node ever
          $min_date = $this->getBoMonth($timestamp);
          $now_date = $this->getBoMonth(\Drupal::time()->getRequestTime());

          // find how many months difference to reach current date
          $count_months = $this->getMonthsDifference($min_date, $now_date);

          $dates_sitemap = $this->fillSitemapMonths($count_months, $min_date, $options['exclude_current_month']);
          break;
      }
    }

    // shift off the current month
    if (!$count) {
      $date_sitemap = array_shift($dates_sitemap);
    }
    else {
      // if count query just initialize so there is no warning in php
      $date_sitemap = '';
    }

    // after fixing the dates we can set the query condition
    if (!empty($date_sitemap)) {
      $query->condition('nfd.created', $date_sitemap['timestamp']['min_date'], '>=')
        ->condition('nfd.created', $date_sitemap['timestamp']['max_date'], '<=');
    }

    // limit is probably not used anymore but leaving it here, for now
    if ($limit != 0) {
      $query->range(0, $limit);
    }

    // if it's a count query, rewrite the whole query before execution
    if ($count) {
      $query = $connection->select('node_field_data', 'nfd');
      $query->condition('status', 1, '=')
        ->condition('type', 'article_liberal')
        ->fields('nfd', ['nid', 'created']);

      if (!empty($dates_sitemap)) {
        $first = reset($dates_sitemap);
        $last = end($dates_sitemap);
        $query->condition('created', $first['timestamp']['min_date'], '>=')
          ->condition('created', $last['timestamp']['max_date'], '<=');
      }
    }

    if ($count) {
      $results = $query->countQuery()->execute()->fetchField();
    }
    else {
      $results = $query->execute()->fetchAllAssoc('nid', \Drupal\Core\Database\Statement\FetchAs::Associative);
    }

    $combined_results = [
      'results' => $results,
      'dates_sitemap' => $dates_sitemap,
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
  * First and last day of each month that needs to be generated
  */
  private function fillSitemapDay($min_date, $max_date): array {
    $dates_sitemap = [];

    $key = $min_date->format('Y-m-d');
    $dates_sitemap[$key] = [
      'readable' => [
        'min_date' => $min_date->format('d-m-Y H:i:s'),
        'max_date' => $max_date->format('d-m-Y H:i:s'),
      ],
      'timestamp' => [
        'min_date' => $min_date->getTimestamp(),
        'max_date' => $max_date->getTimestamp(),
      ],
      'filename_fragment' => 'd_' . $key,
    ];

    return $dates_sitemap;
  }

  /**
  * @param int $count_months
  * integer showing how many months have passed
  * @param DateTime $min_date
  * the first day of the month of the date we want to produce sitemaps for
  * @return array
  * First and last day of each month that needs to be generated
  */
  private function fillSitemapMonths($count_months, $min_date, $exclude_date = NULL): array {
    $i = 0;
    $dates_sitemap = [];

    $max_date = $this->getEoMonth($min_date->getTimestamp());

    if ($count_months > 0) {
      $current_date = $this->getBoMonth(\Drupal::time()->getRequestTime());
      $current_month_key = $current_date->format('Y-m');
      $next_month_key = $current_date->modify('first day of next month')->format('Y-m');
    }

    while ($i <= $count_months) {
      $key = $min_date->format('Y-m');
      $min_key = $min_date->format('d-m-Y H:i:s');
      $max_key = $max_date->format('t-m-Y H:i:s');

      $dates_sitemap[$key] = [
        'readable' => [
          'min_date' => $min_key,
          'max_date' => $max_key,
        ],
        'timestamp' => [
          'min_date' => $min_date->getTimestamp(),
          'max_date' => $max_date->getTimestamp(),
        ],
        'filename_fragment' => $key,
      ];

      $min_date->modify('first day of next month');
      $max_date->modify('last day of next month');
      $i++;
    }

    // if there is an extra month, remove it
    if (isset($next_month_key) && isset($dates_sitemap[$next_month_key])) {
      unset($dates_sitemap[$next_month_key]);
    }

    // if the script option is set to exclude the current month, the remove it
    if (isset($exclude_date)) {
      unset($dates_sitemap[$current_month_key]);
    }

    return $dates_sitemap;
  }

  /**
  * It is important to remember, if we want months difference, if the
  * days and time are not the same we will probably get 2 month difference instead of 1
  * so in this case set both dates to for ex. 01-04-2023 00:00:00 and 01-02-2023 00:00:00
  * before comparing as this will give us 2 instead of 3 if the hours etc. differ
  * @param Datetime $d1
  * @param DateTime $d2
  * @return int
  * How many months difference the two dates have
  */
  public function getMonthsDifference($d1, $d2) {
    // get the date interval object
    $interval = $d1->diff($d2);

    $count_months = +$interval->m + ($interval->y * 12);

    // if there's days remaining add another month
    if ($interval->d > 0) {
      $count_months++;
    }

    return $count_months;
  }

  public function FixInputDate($time) {
    // No date provided — silent fallback so DI boot / updatedb / cron do not crash.
    if (empty($time)) {
      return new \DateTime('today', new \DateTimeZone("Europe/Athens"));
    }

    // Date provided — must be valid format. Surface bad CLI input loudly.
    $date = \DateTime::createFromFormat('d-m-Y', $time, new \DateTimeZone("Europe/Athens"));
    if (!$date) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid --date value %s; expected format d-m-Y',
        var_export($time, TRUE)
      ));
    }
    $date->modify('today');
    return $date;
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

  public function getBoMonth($timestamp) {
    $time = new \DateTime();
    $time->setTimezone(new \DateTimeZone('Europe/Athens'));
    $time->setTimestamp($timestamp);
    //First day of month, first minute, second etc
    $time->modify('first day of this month')->modify('today');
    return $time;
  }

  public function getEoMonth($timestamp) {
    //Last day of month
    $time = new \DateTime();
    $time->setTimezone(new \DateTimeZone('Europe/Athens'));
    $time->setTimestamp($timestamp);

    $time->modify('last day of this month');
    $time = $this->getEoDay($time->getTimestamp());
    return $time;
  }

}
