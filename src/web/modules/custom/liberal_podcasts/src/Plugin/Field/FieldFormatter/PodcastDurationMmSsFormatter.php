<?php

namespace Drupal\liberal_podcasts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;

/**
 * @FieldFormatter(
 *   id = "podcast_duration_mm_ss",
 *   label = @Translation("MM:SS Podcast Duration"),
 *   field_types = {
 *     "integer",
 *     "decimal",
 *     "float"
 *   }
 * )
 */
final class PodcastDurationMmSsFormatter extends FormatterBase {

  /**
   * @return array{'#markup': mixed}[]
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $seconds = (int) round($item->value ?? 0);
      $minutes = intdiv($seconds, 60);
      $remaining = $seconds % 60;
      $elements[$delta] = [
        '#markup' => Markup::create(sprintf('%02d:%02d', $minutes, $remaining)),
      ];
    }
    return $elements;
  }

}
