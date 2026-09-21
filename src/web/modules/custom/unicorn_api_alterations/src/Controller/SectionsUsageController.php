<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Επιστρέφει in-use flag για κάθε Section με μία κλήση.
 *
 * Ένα section θεωρείται "in use" όταν το uuid του εμφανίζεται στο home-layout
 * blob κάποιου variant. Χρησιμεύει στο dashboard για το enable/disable του
 * Delete (US 3.4) και το warning στο Edit (US 3.3).
 */
class SectionsUsageController extends ControllerBase {

  public function usage(): JsonResponse {
    $used = [];
    foreach (['liberal', 'markets'] as $variant) {
      $layout = \Drupal::state()->get('liberal.home_page_editor.' . $variant, []);
      foreach ($layout as $section) {
        if (!empty($section['uuid'])) {
          $used[$section['uuid']] = TRUE;
        }
      }
    }

    $result = [];
    $storage = \Drupal::entityTypeManager()->getStorage('section');
    foreach ($storage->loadMultiple() as $entity) {
      $result[$entity->uuid()] = isset($used[$entity->uuid()]);
    }
    return new JsonResponse($result);
  }

}
