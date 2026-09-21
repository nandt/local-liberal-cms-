<?php

declare(strict_types=1);

namespace Drupal\unicorn_opinions\Routing;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_opinions\Controllers\AdminOpinions;
use Drupal\unicorn_opinions\Permissions;
use Drupal\unicorn_opinions\Resources\OpinionsResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RoutesProvider extends BaseRoutesProvider
{

    protected function register(RouteCollection $routes): RouteCollection
    {
        $routes->add(
                'opinions',
                new Route(
                        path: '/%jsonapi%/opinions',
                        defaults: [
                                '_jsonapi_resource' => OpinionsResource::class,
                                '_jsonapi_resource_types' => ['node--article_liberal'],
                        ],
                        requirements: [
                                '_permission' => 'access content',
                        ],
                        methods: ['GET'],
                )
        );

        $routes->add(
                'get_settings',
                new Route(
                        path: '/customapi/admin/opinions',
                        defaults: [
                                '_controller' => $this->controller([AdminOpinions::class, 'getSettings']),
                        ],
                        requirements: [
                                '_permission' => Permissions::Administer->value,
                        ],
                        methods: ['GET'],
                )
        );

        $routes->add(
                'change_settings',
                new Route(
                        path: '/customapi/admin/opinions',
                        defaults: [
                                '_controller' => $this->controller([AdminOpinions::class, 'changeSettings']),
                        ],
                        requirements: [
                                '_permission' => Permissions::Administer->value,
                        ],
                        methods: ['POST'],
                )
        );

        $routes->addNamePrefix('unicorn_opinions.');

        return $routes;
    }

}
