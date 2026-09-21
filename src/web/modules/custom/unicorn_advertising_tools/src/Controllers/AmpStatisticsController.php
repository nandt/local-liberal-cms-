<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Services\AdTracker;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class AmpStatisticsController extends ControllerBase {

  public function __construct(
    private readonly AdTracker $adTracker,
    private readonly ExtendedValidator $validator,
    private readonly AdvertisingToolsConfig $advertisingToolsConfig,
  ) {
  }

  public function impressions(Request $request): Response {
    if ($request->getMethod() === 'OPTIONS' && $this->isAmpOrigin($request)) {
      return $this->preflightResponse($request);
    }

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

    return $this->corsResponse($request, ResponseBuilder::make()->json());
  }

  public function clicks(Request $request): Response {
    if ($request->getMethod() === 'OPTIONS' && $this->isAmpOrigin($request)) {
      return $this->preflightResponse($request);
    }

    $nodeId = (int) $request->input('nid');

    $this->validator->validateAndThrow($nodeId, [
      new Type('integer'),
      new Positive(),
      new NotBlank(),
    ]);

    $this->adTracker->countClicks($nodeId);

    return $this->corsResponse($request, ResponseBuilder::make()->json());
  }

  private function isAmpOrigin(Request $request): bool {
    $ampPublisherId = $this->advertisingToolsConfig->getAmpPublisherId();

    return $ampPublisherId !== null && $request->headers->get('origin', '') === $ampPublisherId;
  }

  private function preflightResponse(Request $request): Response {
    return $this->corsResponse($request, new Response('', Response::HTTP_NO_CONTENT));
  }

  /**
   * don't add this to the builder. AMP is a legacy thing.
   */
  private function corsResponse(Request $request, Response $response): Response {
    if ($this->isAmpOrigin($request)) {
      $origin = $request->headers->get('origin', '');
      $response->headers->set('Access-Control-Allow-Origin', $origin);
      $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
      $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Origin');
    }

    return $response;
  }

}
