<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Resources;

use Drupal\Core\Datetime\DateFormatterInterface;

abstract class BaseResource {

  public function __construct(
    private readonly DateFormatterInterface $dateFormatter
  ) {}

  protected function getTimestampAsHtmlDatetime(int $date): ?string {
    if ($date === 0) {
      return null;
    }

    return $this->dateFormatter->format($date, 'html_datetime');
  }
}
