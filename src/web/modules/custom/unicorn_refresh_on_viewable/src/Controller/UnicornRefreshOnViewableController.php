<?php

declare(strict_types=1);

namespace Drupal\unicorn_refresh_on_viewable\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;

final class UnicornRefreshOnViewableController extends ControllerBase {

  private readonly Config $config;

  /**
   * Constructs a new controller instance.
   */
  public function __construct(
    private readonly ExtendedValidator $validator,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->getEditable('unicorn_refresh_on_viewable.settings');
  }

  /**
   * Saves the Refresh on Viewable setting.
   */
  public function alterSettings(Request $request): JsonResponse {
    $payload = $request->getPayload();
    $enabled_home_input = $payload->get('enabled_home');

    $violations = $this->validator->validate($enabled_home_input, [
      new NotNull(),
      new Choice(choices: [TRUE, FALSE, 1, 0, '1', '0', 'true', 'false']),
    ]);
    if (count($violations) > 0) {
      $errors = [];
      foreach ($violations as $violation) {
        $errors[] = $violation->getMessage();
      }

      return ResponseBuilder::make([
        'errors' => ['enabled_home' => $errors],
      ], Response::HTTP_UNPROCESSABLE_ENTITY)->json();
    }

    $enabled_home = $payload->getBoolean('enabled_home');
    $this->config
      ->set('enabled_home', $enabled_home)
      ->save();

    return ResponseBuilder::make([
      'enabled_home' => $enabled_home,
      'message' => $enabled_home
        ? 'The Refresh on Viewable was successfully activated.'
        : 'The Refresh on Viewable was successfully disabled.',
    ])->json();
  }

  /**
   * Returns the Refresh on Viewable setting.
   */
  public function getSettings(): JsonResponse {
    return ResponseBuilder::make([
      'enabled_home' => (bool) $this->configFactory
        ->get('unicorn_refresh_on_viewable.settings')
        ->get('enabled_home'),
    ])->json();
  }

}
