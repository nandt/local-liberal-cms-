<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Routing;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_search\Controllers\SearchController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'index',
      new Route(
        path: '/search',
        defaults: [
          '_controller' => $this->controller([SearchController::class, 'index']),
        ],
        requirements: [
          '_access' => 'TRUE',
        ],
        methods: ['GET'],
      ),
    );

    $routes->addPrefix('/customapi');
    $routes->addNamePrefix('unicorn_search.');

    return $routes;
  }

}
