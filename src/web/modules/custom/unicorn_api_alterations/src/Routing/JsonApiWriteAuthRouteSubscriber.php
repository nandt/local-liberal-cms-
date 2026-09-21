<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Makes JSON:API write routes token-only: drops cookie auth and the CSRF header.
 */
final class JsonApiWriteAuthRouteSubscriber extends RouteSubscriberBase {

  private const array ACCESS_REQUIREMENTS = [
    '_access',
    '_entity_access',
    '_entity_create_access',
    '_permission',
    '_role',
    '_jsonapi_relationship_route_access',
  ];

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection as $name => $route) {
      if (!str_starts_with($name, 'jsonapi.')) {
        continue;
      }
      if ($route->getRequirement('_csrf_request_header_token') === NULL) {
        continue;
      }

      $auth = $route->getOption('_auth');
      if (is_array($auth)) {
        $route->setOption('_auth', array_values(array_diff($auth, ['cookie'])));
      }

      $requirements = $route->getRequirements();
      unset($requirements['_csrf_request_header_token']);

      if (!array_intersect_key($requirements, array_flip(self::ACCESS_REQUIREMENTS))) {
        $requirements['_access'] = 'TRUE';
      }

      $route->setRequirements($requirements);
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -1024];

    return $events;
  }

}
