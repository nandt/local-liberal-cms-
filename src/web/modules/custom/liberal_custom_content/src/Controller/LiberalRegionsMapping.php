<?php

namespace Drupal\liberal_custom_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\liberal_custom_content\Utils\liberalCustomContentUtils;

class LiberalRegionsMapping extends ControllerBase {

  public function getRegionsMapping() {

    if ($cache = \Drupal::cache()->get(liberalCustomContentUtils::regionsCacheId)) {
      $regions = $cache->data;
    }
    else {
      $regions = liberalCustomContentUtils::getRegionsWeightsMapping();
    }

    $data['data'] = $regions;

    // Add Cache settings for Max-age, tags
    $data['#cache'] = [
      'max-age' => Cache::PERMANENT,
      'tags' => liberalCustomContentUtils::regionsCacheTags,
    ];

    $response = new CacheableJsonResponse($data, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($data));

    return $response;
  }

}
