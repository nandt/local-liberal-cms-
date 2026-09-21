<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_advertising_tools\Services\AdTracker;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Http\RequestParams;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class StatisticsController extends ControllerBase {

  public function __construct(
    private readonly AdTracker $adTracker,
    private readonly ExtendedValidator $validator,
  ) {}

  public function impressions(Request $request): Response {
    $nodeIds = array_map(intval(...), (array) $request->input('nids'));

    $this->validator->validateAndThrow($nodeIds, [
      new NotBlank(),
      new All([
        new Type('integer'),
        new Positive(),
      ]),
    ]);

    foreach ($nodeIds as $nid) {
      $this->adTracker->addToQueueImpressions($nid);
    }

    return ResponseBuilder::make()->json();
  }

  public function clicks(int $nid): Response {
    $this->adTracker->countClicks($nid);

    return ResponseBuilder::make()->json();
  }

  public function views(int $nid): JsonResponse {
    $this->adTracker->addToQueueStatistics($nid);

    return ResponseBuilder::make()->json();
  }

}
