<?php

namespace Drupal\liberal_podcasts\Utils;

class LiberalPodcastDurationUtility {

  public static function getDuration($duration): ?string {
    if (!is_numeric($duration) || $duration <= 0) {
      return 'PT0S';
    }

    $duration_seconds = (int) round($duration);

    $hours = intdiv($duration_seconds, 3600);
    $minutes = intdiv($duration_seconds % 3600, 60);
    $seconds = $duration_seconds % 60;

    $parts = [];
    if ($hours > 0) {
      $parts[] = $hours . 'H';
    }
    if ($minutes > 0) {
      $parts[] = $minutes . 'M';
    }
    if ($seconds > 0 || empty($parts)) {
      $parts[] = $seconds . 'S';
    }

    return 'PT' . implode('', $parts);
  }

}
