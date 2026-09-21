<?php

declare(strict_types=1);

namespace Drupal\unicorn_lazy_load_adtools\Routing;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_lazy_load_adtools\Controller\UnicornLazyLoadAdToolsController;
use Drupal\unicorn_lazy_load_adtools\Permissions;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  /**
   * {@inheritdoc}
   */
  protected function register(RouteCollection $routes): RouteCollection {
    $routes->add(
      'unicorn_lazy_load_adtools.alter_settings',
      new Route(
        path: '/customapi/lazy-load-adtools/alter-settings',
        defaults: [
          '_title' => 'Alter Settings of Lazy Loading',
          '_controller' => $this->controller([
            UnicornLazyLoadAdToolsController::class,
            'alterSettings',
          ]),
        ],
        requirements: [
          '_permission' => Permissions::CHANGE_LAZY_LOAD_ADTOOLS_SETTINGS->value,
        ],
        methods: ['POST'],
      )
    );

    $routes->add(
      'unicorn_lazy_load_adtools.get_settings',
      new Route(
        path: '/customapi/lazy-load-adtools/get-settings',
        defaults: [
          '_title' => 'Get Settings of Lazy loading',
          '_controller' => $this->controller([
            UnicornLazyLoadAdToolsController::class,
            'getSettings',
          ]),
        ],
        requirements: [
          '_permission' => Permissions::CHANGE_LAZY_LOAD_ADTOOLS_SETTINGS->value,
        ],
        methods: ['GET'],
      )
    );

    return $routes;
  }

}
