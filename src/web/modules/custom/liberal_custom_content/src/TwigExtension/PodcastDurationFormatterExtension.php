<?php

namespace Drupal\liberal_custom_content\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PodcastDurationFormatterExtension extends AbstractExtension {

  /**
   * @return \Twig\TwigFilter[]
   */
  #[\Override]
  public function getFilters() {
    return [
      new TwigFilter('podcast_duration_format', $this->formatDuration(...)),
    ];
  }

  public function formatDuration($seconds) {
    if (empty($seconds) || !is_numeric($seconds)) {
      return '00:00';
    }

    $seconds = (int) $seconds;
    $minutes = intval($seconds / 60);
    $remainingSeconds = $seconds % 60;

    return sprintf('%02d:%02d', $minutes, $remainingSeconds);
  }

}
