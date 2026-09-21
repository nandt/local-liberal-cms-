<?php

namespace Drupal\unicorn_lazy_load_adtools\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Choice;

final class UnicornLazyLoadAdToolsController extends ControllerBase {

  private readonly Config $config;

  /**
   * Constructs a new controller instance.
   */
  public function __construct(
    private readonly ExtendedValidator $validator,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->getEditable('unicorn_lazy_load_adtools.settings');
  }

  /**
   * Saves the lazy load setting.
   */
  public function alterSettings(Request $request): JsonResponse {
    $enabled_home = (bool) $this->config->get('enabled_home');
    $enabled_home_input = $request->input('enabled_home');

    if (!is_null($enabled_home_input)) {
      $this->validator->validateAndThrow($enabled_home_input, [
        new Choice(choices: [TRUE, FALSE, 1, 0, '1', '0']),
      ]);
      $enabled_home = (bool) $enabled_home_input;
      $this->config->set('enabled_home', $enabled_home)->save();
    }

    return ResponseBuilder::make([
      'enabled_home' => $enabled_home,
      'message' => $enabled_home
        ? 'The Lazy Load was successfully activated.'
        : 'The Lazy Load was successfully disabled.',
      'lazy_load' => [
        'fetchMarginPercent' => 800,
        'renderMarginPercent' => 400,
        'mobileScaling' => 1,
      ],
    ])->json();
  }

  /**
   * Returns the lazy load setting.
   */
  public function getSettings(): JsonResponse {
    return ResponseBuilder::make([
      'enabled_home' => (bool) $this->configFactory
        ->get('unicorn_lazy_load_adtools.settings')
        ->get('enabled_home'),
      'lazy_load' => [
        'fetchMarginPercent' => 800,
        'renderMarginPercent' => 400,
        'mobileScaling' => 1,
      ],
    ])->json();
  }

}
