<?php

declare(strict_types=1);

namespace Drupal\unicorn_opinions\Controllers;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Drupal\unicorn_opinions\Resources\AdminOpinionsResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Provides a controller for administering opinions.
 */
class AdminOpinions extends ControllerBase
{
    private readonly Config $config;

    public function __construct(
        private readonly ExtendedValidator $validator,
        ConfigFactoryInterface $configFactory,
        private readonly AdminOpinionsResource $adminOpinionsResource
    ) {
        $this->configFactory = $configFactory;
        $this->config = $this->configFactory->getEditable('unicorn_opinions.settings');
    }

  /**
   * Alter the settings of opinions.
   */
    public function changeSettings(Request $request): JsonResponse
    {
      $opinionCategoryIds = $request->input('ids', []);

        $this->validator->validateAndThrow($opinionCategoryIds, [
          new Type('array'),
          new Count(9),
          new All([
            new AtLeastOneOf([
              new IsNull(),
              new Sequentially([
                new Type('numeric'),
                new GreaterThanOrEqual(1),
              ]),
            ]),
          ]),
        ]);

        $this->config->set('opinions', $opinionCategoryIds);
        $this->config->save();

        return ResponseBuilder::make()->json();
    }

  /**
   * Get the settings of opinions.
   */
    public function getSettings(): JsonResponse
    {
      $response = $this->adminOpinionsResource->toDashboardApi($this->configFactory
        ->get('unicorn_opinions.settings')
        ->get('opinions'));

        return ResponseBuilder::make($response)->json();
    }
}
