<?php

declare(strict_types=1);

namespace Drupal\unicorn_promotional_banner\Routing\Admin;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_promotional_banner\Controller\PromotionalBanner;
use Drupal\unicorn_promotional_banner\Permissions;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'index',
      new Route(
        path: '/customapi/promotional-banner',
        defaults: [
          '_controller' => $this->controller([PromotionalBanner::class, 'index']),
        ],
        requirements: [
          '_permission' => Permissions::Administer->value . '+' . Permissions::View->value,
        ],
        methods: ['GET'],
      ),
    );

    $routes->add(
      'update',
      new Route(
        path: '/customapi/promotional-banner',
        defaults: [
          '_controller' => $this->controller([PromotionalBanner::class, 'update']),
        ],
        requirements: [
          '_permission' => Permissions::Administer->value,
        ],
        methods: ['POST'],
      ),
    );

    $routes->add(
      'delete_image',
      new Route(
        path: '/customapi/promotional-banner/image',
        defaults: [
          '_controller' => $this->controller([PromotionalBanner::class, 'deleteImage']),
        ],
        requirements: [
          '_permission' => Permissions::Administer->value,
        ],
        methods: ['DELETE'],
      ),
    );

    $routes->addNamePrefix('unicorn_promotional_banner.admin.');

    return $routes;
  }

}
