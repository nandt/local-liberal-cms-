<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Routing;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_video_article\Form\UnicornVideoArticleSettingsForm;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  protected function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'admin_settings',
      new Route(
        path: '/admin/config/unicorn_video_article/settings',
        defaults: [
          '_form' => UnicornVideoArticleSettingsForm::class,
          '_title' => 'Unicorn Video Article Settings',
        ],
        requirements: [
          '_permission' => 'administer site configuration',
        ],
      ),
    );

    $routes->addNamePrefix('unicorn_video_article.');

    return $routes;
  }

}
