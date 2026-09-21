<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Routing;

use Drupal\unicorn_advertising_tools\Controllers\AmpStatisticsController;
use Drupal\unicorn_advertising_tools\Controllers\StatisticsController;
use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class StatisticsRoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'impressions',
      new Route(
        path: '/track/impressions',
        defaults: [
          '_controller' => $this->controller([StatisticsController::class, 'impressions']),
        ],
        requirements: [
          '_access' => 'TRUE',
        ],
        methods: ['POST'],
      ),
    );

    $routes->add(
      'clicks',
      new Route(
        path: '/track/clicks/{nid}',
        defaults: [
          '_controller' => $this->controller([StatisticsController::class, 'clicks']),
        ],
        requirements: [
          '_access' => 'TRUE',
          'nid' => '\d+',
        ],
        methods: ['POST'],
      ),
    );

    $routes->add(
      'statistics',
      new Route(
        path: '/track/views/{nid}',
        defaults: [
          '_controller' => $this->controller([StatisticsController::class, 'views']),
        ],
        requirements: [
          '_access' => 'TRUE',
          'nid' => '\d+',
        ],
        methods: ['POST'],
      ),
    );

    $routes->add(
      'amp.impressions',
      new Route(
        path: '/amp/track/impressions',
        defaults: [
          '_controller' => $this->controller([AmpStatisticsController::class, 'impressions']),
        ],
        requirements: [
          '_access' => 'TRUE',
        ],
        methods: ['POST', 'OPTIONS'],
      ),
    );

    $routes->add(
      'amp.clicks',
      new Route(
        path: '/amp/track/clicks',
        defaults: [
          '_controller' => $this->controller([AmpStatisticsController::class, 'clicks']),
        ],
        requirements: [
          '_access' => 'TRUE',
        ],
        methods: ['POST', 'OPTIONS'],
      ),
    );

    $routes->addPrefix('/customapi/advertising-tools');
    $routes->addNamePrefix('unicorn_advertising_tools.statistics.');

    return $routes;
  }

}
