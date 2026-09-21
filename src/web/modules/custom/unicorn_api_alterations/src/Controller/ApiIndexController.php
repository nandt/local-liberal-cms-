<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiIndexController extends ControllerBase {

  public function __construct(protected RouteProviderInterface $routeProvider) {}

  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self($container->get('router.route_provider'));
  }

  public function index(Request $request): JsonResponse {
    $base = $request->getSchemeAndHttpHost();
    $groups = [];

    foreach ($this->routeProvider->getAllRoutes() as $name => $route) {
      $path = $route->getPath();
      $methods = $route->getMethods() ?: ['GET'];
      $perm = $route->getRequirement('_permission') ?? ($route->getRequirement('_access') === 'TRUE' ? 'public' : '');

      $group = NULL;
      if (str_starts_with($path, '/customapi/')) {
        $group = 'customapi';
      }
      elseif (str_starts_with($path, '/oauth/')) {
        $group = 'oauth';
      }
      elseif (str_starts_with($path, '/api/') && str_starts_with((string) $name, 'liberal_')) {
        $group = 'legacy_api';
      }
      elseif (str_starts_with($path, '/' . \Drupal::config('jsonapi_extras.settings')->get('path_prefix') . '/')) {
        $group = 'jsonapi';
      }
      elseif ($path === '/' . \Drupal::config('jsonapi_extras.settings')->get('path_prefix')) {
        $group = 'jsonapi';
      }

      if (!$group) {
        continue;
      }

      $groups[$group][] = [
        'name' => $name,
        'path' => $path,
        'methods' => $methods,
        'permission' => $perm,
        'url' => $base . $path,
      ];
    }

    foreach ($groups as &$g) {
      usort($g, fn($a, $b): int => strcmp((string) $a['path'], (string) $b['path']));
    }

    $response = new JsonResponse([
      'meta' => [
        'base_url' => $base,
        'description' => 'Catalog of all APIs (custom + JSON:API + OAuth + legacy)',
        'counts' => array_map(count(...), $groups),
      ],
      'customapi' => $groups['customapi'] ?? [],
      'jsonapi' => [
        'catalog_url' => $base . '/' . \Drupal::config('jsonapi_extras.settings')->get('path_prefix'),
        'note' => 'JSON:API auto-discovers resources. Open catalog_url to see all enabled entity types.',
        'routes' => $groups['jsonapi'] ?? [],
      ],
      'oauth' => $groups['oauth'] ?? [],
      'legacy_api' => $groups['legacy_api'] ?? [],
    ]);
    $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $response;
  }

}
