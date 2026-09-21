<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Routing;

use Drupal\liberal_amp_loader\Controller\LiberalAmpLoaderGetJson;
use Drupal\liberal_amp_loader\Form\AmpLoaderSettingsForm;
use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  protected function register(RouteCollection $routes): RouteCollection {
    $routes->add(
      'ampjson',
      new Route(
        path: '/get/ampjson/{nid}/list',
        defaults: [
          '_controller' => $this->controller([LiberalAmpLoaderGetJson::class, 'getAmpNextPageList']),
        ],
        requirements: [
          '_permission' => 'access content',
        ],
      ),
    );

    $routes->add(
      'admin_settings',
      new Route(
        path: '/liberal-amp-loader/form/settings',
        defaults: [
          '_form' => AmpLoaderSettingsForm::class,
          '_title' => 'Amp Loader Settings',
        ],
        requirements: [
          '_permission' => 'administer site configuration',
        ],
      ),
    );

    $routes->addNamePrefix('liberal_amp_loader.');

    return $routes;
  }

}
