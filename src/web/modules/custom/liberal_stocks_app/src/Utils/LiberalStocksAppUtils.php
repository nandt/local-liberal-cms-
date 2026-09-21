<?php

namespace Drupal\liberal_stocks_app\Utils;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use DateTimeZone;

class LiberalStocksAppUtils {

  public static function getFormattedQueryDate(string $date): int
  {
    $datetime = new DrupalDateTime($date, new DateTimeZone("Europe/Athens"));
    $timezone = new DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $datetime->setTimezone($timezone);

    return $datetime->getTimestamp();
  }

  /**
   * Whether the date given was a timestamp
   *
   * @return array{value: string, object: DrupalDateTime}
   */
  // time in UTC+0 is taken for granted here
  public static function getFormattedDate(int|string $date, bool $timestamp = true): array
  {
    $site_timezone = new DateTimeZone('Europe/Athens');
    $timezone = new DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

    $datetime = match (true) {
      $timestamp && is_numeric($date) => DrupalDateTime::createFromTimestamp((int) $date, $timezone),
      default => new DrupalDateTime((string) $date, $timezone),
    };

    // set timezone to storage timezone. Because otherwise site timezone is used
    $datetime->setTimezone($site_timezone);

    // fixed date for drupal format
    return [
      'value' => $datetime->format("d/m/Y H:i"),
      'object' => $datetime
    ];
  }

  /**
   * @return array{
   *   current_page: int,
   *   items_per_page: int,
   *   total_items: ?int,
   *   total_pages: ?int
   * }
   */
  public static function getPagerInfo(int $page, int $items_per_page, ?int $total_items = NULL, ?int $total_pages = NULL): array {

    return [
      'current_page' => $page,
      'items_per_page' => $items_per_page,
      'total_items' => $total_items,
      'total_pages' => $total_pages
    ];
  }

}
