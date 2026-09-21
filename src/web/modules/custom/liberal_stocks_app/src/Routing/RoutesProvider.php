<?php

declare(strict_types=1);

namespace Drupal\liberal_stocks_app\Routing;

use Drupal\liberal_stocks_app\Controller\MarketsBackendController;
use Drupal\liberal_stocks_app\Controller\MarketsFrontendController;
use Drupal\liberal_stocks_app\Controller\NotificationsSendController;
use Drupal\liberal_stocks_app\Form\LiberalStocksAppSettingsForm;
use Drupal\liberal_stocks_app\Permissions;
use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'admin_settings',
      new Route(
        path: '/admin/liberal-stocks-app/form/settings',
        defaults: [
          '_form' => LiberalStocksAppSettingsForm::class,
          '_title' => 'Liberal Stocks App Settings',
        ],
        requirements: [
          '_permission' => 'administer site configuration',
        ],
      ),
    );

    $routes->add(
      'notifications_send',
      new Route(
        path: '/api/stocks-app/notifications/{node}',
        defaults: [
          '_controller' => $this->controller([
            NotificationsSendController::class,
            'sendNotification',
          ]),
        ],
        requirements: [
          '_permission' => Permissions::SendNotifications->value,
        ],
        methods: ['POST'],
      ),
    );

    $routes->add(
      'markets_fe',
      new Route(
        path: '/api/stocks-app/stocks',
        defaults: [
          '_controller' => $this->controller([
            MarketsFrontendController::class,
            'getMarketsArticles',
          ]),
        ],
        requirements: [
          '_permission' => 'access content',
        ],
        methods: ['GET'],
      ),
    );

    $routes->add(
      'markets_be',
      new Route(
        path: '/api/stocks-app/stocks-backend',
        defaults: [
          '_controller' => $this->controller([
            MarketsBackendController::class,
            'getMarketsArticles',
          ]),
        ],
        requirements: [
          '_permission' => 'access content',
        ],
        methods: ['GET'],
      ),
    );

    $routes->addNamePrefix('liberal_stocks_app.');

    return $routes;
  }

}
