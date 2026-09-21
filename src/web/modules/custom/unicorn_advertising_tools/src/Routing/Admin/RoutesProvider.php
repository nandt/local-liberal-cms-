<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Routing\Admin;

use Drupal\unicorn_advertising_tools\Controllers\Admin\AdvertisingToolsController;
use Drupal\unicorn_advertising_tools\Permissions;
use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'index',
      new Route(
        path: '/admin',
        defaults: [
          '_controller' => $this->controller([AdvertisingToolsController::class, 'index']),
        ],
        requirements: [
          '_permission' => Permissions::View->value . '+' . Permissions::Administer->value,
        ],
        methods: ['GET'],
      ),
    );

    $routes->add(
      'show',
      new Route(
        path: '/admin/{entityId}',
        defaults: [
          '_controller' => $this->controller([AdvertisingToolsController::class, 'show']),
        ],
        requirements: [
          '_permission' => Permissions::View->value . '+' . Permissions::Administer->value,
          'entityId' => '\d+',
        ],
        methods: ['GET'],
      ),
    );

    $routes->add(
      'store',
      new Route(
        path: '/admin',
        defaults: [
          '_controller' => $this->controller([AdvertisingToolsController::class, 'store']),
        ],
        requirements: [
          '_permission' => Permissions::Administer->value,
        ],
        methods: ['POST'],
      ),
    );

    $routes->add(
      'update',
      new Route(
        path: '/admin/{entityId}',
        defaults: [
          '_controller' => $this->controller([AdvertisingToolsController::class, 'update']),
        ],
        requirements: [
          '_permission' => Permissions::Administer->value,
          'entityId' => '\d+',
        ],
        methods: ['PATCH'],
      ),
    );

    $routes->add(
      'destroy',
      new Route(
        path: '/admin/{entityId}',
        defaults: [
          '_controller' => $this->controller([AdvertisingToolsController::class, 'destroy']),
        ],
        requirements: [
          '_permission' => Permissions::Administer->value,
          'entityId' => '\d+',
        ],
        methods: ['DELETE'],
      ),
    );

    $routes->addPrefix('/customapi/advertising-tools');
    $routes->addNamePrefix('unicorn_advertising_tools.admin.');

    return $routes;
  }

}
