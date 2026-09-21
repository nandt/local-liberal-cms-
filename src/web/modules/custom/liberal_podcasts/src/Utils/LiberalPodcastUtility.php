<?php

namespace Drupal\liberal_podcasts\Utils;

class LiberalPodcastUtility {

  public static function extractVideoId(string $source_code): ?string {
    $match_result = preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $source_code, $matches);

    if (!$match_result) {
      return NULL;
    }

    $url = $matches[1];
    $parts = parse_url($url);
    $segments = $parts['path'] ? explode('/', $parts['path']) : NULL;
    $video_id = $segments[1] ?? NULL;
    return $video_id;
  }

  public static function fix_facebook_iframe_dimensions(string $html, int $width = 1280, int $height = 720): string {
    // Replace width/height in Facebook iframe src
    $html = preg_replace('/(facebook\.com\/plugins\/video\.php[^"]*width=)\d+/', '${1}' . $width, $html);
    $html = preg_replace('/(facebook\.com\/plugins\/video\.php[^"]*height=)\d+/', '${1}' . $height, (string) $html);

    // Replace iframe width/height attributes
    $html = preg_replace('/(<iframe[^>]*facebook\.com\/plugins\/video\.php[^>]*width=")\d+(")/', '${1}' . $width . '${2}', (string) $html);
    $html = preg_replace('/(<iframe[^>]*facebook\.com\/plugins\/video\.php[^>]*height=")\d+(")/', '${1}' . $height . '${2}', (string) $html);

    return $html;
  }

}
