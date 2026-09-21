<?php

declare(strict_types=1);

namespace Drupal\unicorn_ai\Routing;

use Drupal\unicorn_ai\Controller\UnicornAiSummaryTranslation;
use Drupal\unicorn_ai\Form\UnicornAiAdminForm;
use Drupal\unicorn_ai\Permissions\Permissions;
use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  protected function register(RouteCollection $routes): RouteCollection {
    $routes->add(
      'admin_form',
      new Route(
        path: '/admin/unicorn-ai/form',
        defaults: [
          '_form' => UnicornAiAdminForm::class,
          '_title' => 'Unicorn AI Settings',
        ],
        requirements: [
          '_permission' => 'administer site configuration',
        ],
      ),
    );

    $routes->add(
      'get_summary_translation',
      new Route(
        path: '/api/unicorn-ai/get-summary-translation/{type}',
        defaults: [
          '_controller' => $this->controller([UnicornAiSummaryTranslation::class, 'getSummaryTranslation']),
          '_title' => 'Unicorn AI Get Summary & Translation',
        ],
        requirements: [
          '_permission' => Permissions::SummarizeTranslation->value,
        ],
      ),
    );

    $routes->addNamePrefix('unicorn_ai.');

    return $routes;
  }

}
