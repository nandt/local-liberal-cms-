<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Routing;

use Drupal\unicorn_advertising_tools\Resource\ReadMoreArticles;
use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'read_more',
      new Route(
        path: '/%jsonapi%/advertising-tools/read-more/{node}',
        defaults: [
          '_jsonapi_resource' => ReadMoreArticles::class,
          '_jsonapi_resource_types' => ['node--article_liberal'],
        ],
        requirements: [
          '_permission' => 'access content',
        ],
        options: [
          'parameters' => [
            'node' => [
              'type' => 'entity:node',
              'bundle' => ['article_liberal'],
            ],
          ],
        ],
      ),
    );

    $routes->addNamePrefix('unicorn_advertising_tools.');
    return $routes;
  }

}
