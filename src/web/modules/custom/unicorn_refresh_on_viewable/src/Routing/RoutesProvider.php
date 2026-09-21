<?php

declare(strict_types=1);

namespace Drupal\unicorn_refresh_on_viewable\Routing;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_refresh_on_viewable\Controller\UnicornRefreshOnViewableController;
use Drupal\unicorn_refresh_on_viewable\Permissions;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  /**
   * {@inheritdoc}
   */
  protected function register(RouteCollection $routes): RouteCollection {
    $routes->add(
      'alter_settings',
      new Route(
        path: '/customapi/refresh-on-viewable/alter-settings',
        defaults: [
          '_title' => 'Alter Refresh on Viewable settings',
          '_controller' => $this->controller([
            UnicornRefreshOnViewableController::class,
            'alterSettings',
          ]),
        ],
        requirements: [
          '_permission' => Permissions::ChangeRefreshOnViewableSettings->value,
        ],
        methods: ['POST'],
      )
    );

    $routes->add(
      'get_settings',
      new Route(
        path: '/customapi/refresh-on-viewable/get-settings',
        defaults: [
          '_title' => 'Get Refresh on Viewable settings',
          '_controller' => $this->controller([
            UnicornRefreshOnViewableController::class,
            'getSettings',
          ]),
        ],
        requirements: [
          '_permission' => Permissions::ChangeRefreshOnViewableSettings->value,
        ],
        methods: ['GET'],
      )
    );

    $routes->addNamePrefix('unicorn_refresh_on_viewable.');

    return $routes;
  }

}
