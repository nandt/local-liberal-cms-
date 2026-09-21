<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\node\NodeInterface;

/**
 * Extracts field_kodikas_impression_image's <img> src into an amp-pixel.
 */
final class AmpImpressionPixel {

  /**
   * @return array<string, mixed>|null
   */
  public function build(NodeInterface $node): ?array {
    $rawValue = (string) ($node->get('field_kodikas_impression_image')->value ?? '');
    $src = $this->extractImgSrc($rawValue);

    if ($src === NULL) {
      return NULL;
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'amp-pixel',
      '#attributes' => [
        'src' => $src,
      ],
    ];
  }

  private function extractImgSrc(string $rawValue): ?string {
    if (!preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawValue, $matches)) {
      return NULL;
    }

    return $matches[1];
  }

}
