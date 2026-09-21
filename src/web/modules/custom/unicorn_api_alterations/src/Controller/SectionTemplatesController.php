<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Single source of truth για τα predefined section templates.
 *
 * Το dashboard το χρησιμοποιεί για το template dropdown (label + article slots
 * + feature/preview) και ο public renderer για να ξέρει layout + πλήθος άρθρων.
 * Ο ίδιος slot map χρησιμοποιείται και στο hook_section_presave (article_slot).
 */
class SectionTemplatesController extends ControllerBase {

  const TEMPLATES = [
    ['id' => 'home', 'label' => 'Home', 'articleSlots' => 23, 'isFeature' => FALSE, 'frontImage' => FALSE],
    ['id' => 'apopseis', 'label' => 'Απόψεις', 'articleSlots' => 6, 'isFeature' => FALSE, 'frontImage' => FALSE],
    ['id' => 'middle_1', 'label' => '5 articles, 1-1-3', 'articleSlots' => 5, 'isFeature' => FALSE, 'frontImage' => FALSE],
    ['id' => 'middle_3', 'label' => '4 articles, 1-3', 'articleSlots' => 4, 'isFeature' => FALSE, 'frontImage' => FALSE],
    ['id' => 'big_1', 'label' => '9 articles, 3-3-3', 'articleSlots' => 9, 'isFeature' => FALSE, 'frontImage' => FALSE],
    ['id' => 'big_2', 'label' => '10 articles, 3-4-3', 'articleSlots' => 10, 'isFeature' => FALSE, 'frontImage' => FALSE, 'isFeaturePage' => TRUE],
    ['id' => 'afieroma_1', 'label' => 'Features - 3 articles below image', 'articleSlots' => 3, 'isFeature' => TRUE, 'frontImage' => TRUE],
    ['id' => 'afieroma_2', 'label' => 'Features - 3 articles in image', 'articleSlots' => 3, 'isFeature' => TRUE, 'frontImage' => TRUE],
    ['id' => 'afieroma_3', 'label' => 'Ad section - 3 articles', 'articleSlots' => 3, 'isFeature' => FALSE, 'frontImage' => TRUE, 'isAd' => TRUE],
    ['id' => 'liberal_markets', 'label' => 'Liberal Markets', 'articleSlots' => 11, 'isFeature' => FALSE, 'frontImage' => FALSE],
    ['id' => 'makedonika_nea', 'label' => 'Μακεδονικά Νέα', 'articleSlots' => 3, 'isFeature' => FALSE, 'frontImage' => FALSE],
  ];

  public function list(): JsonResponse {
    return new JsonResponse(self::TEMPLATES);
  }

}
