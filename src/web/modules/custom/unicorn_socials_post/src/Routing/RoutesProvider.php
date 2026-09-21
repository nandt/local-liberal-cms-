<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Routing;

use Drupal\unicorn_core\Routing\BaseRoutesProvider;
use Drupal\unicorn_socials_post\Controllers\UnicornSocialsPostFacebook;
use Drupal\unicorn_socials_post\Controllers\UnicornSocialsPostX;
use Drupal\unicorn_socials_post\Forms\UnicornSocialsPostSettingsForm;
use Drupal\unicorn_socials_post\Permissions;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RoutesProvider extends BaseRoutesProvider {

  public function register(RouteCollection $routes): RouteCollection {

    $routes->add(
      'admin_settings',
      new Route(
        path: '/admin/config/unicorn_socials_post/settings',
        defaults: [
          '_form' => UnicornSocialsPostSettingsForm::class,
          '_title' => 'Unicorn Socials Post Settings',
        ],
        requirements: [
          '_permission' => 'administer site configuration',
        ],
      ),
    );

    $routes->add(
      'facebook',
      new Route(
        path: '/customapi/social-share/facebook/{nid}',
        defaults: [
          '_controller' => $this->controller([UnicornSocialsPostFacebook::class, 'post']),
        ],
        requirements: [
          '_permission' => Permissions::Share->value,
        ],
      ),
    );

    $routes->add(
      'twitter',
      new Route(
        path: '/customapi/social-share/x/{nid}',
        defaults: [
          '_controller' => $this->controller([UnicornSocialsPostX::class, 'post']),
        ],
        requirements: [
          '_permission' => Permissions::Share->value,
        ],
      ),
    );

    $routes->addNamePrefix('unicorn_socials_post.');

    return $routes;
  }

}
