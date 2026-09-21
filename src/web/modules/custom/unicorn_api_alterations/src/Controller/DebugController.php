<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class DebugController extends ControllerBase {

  public function status(): JsonResponse {
    $enabled = (bool) \Drupal::state()->get('liberal.debug_mode_enabled', FALSE);
    return new JsonResponse(['enabled' => $enabled]);
  }

}
