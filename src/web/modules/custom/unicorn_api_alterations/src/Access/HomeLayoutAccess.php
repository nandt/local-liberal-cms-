<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Per-variant access for the home-layout endpoint.
 *
 * One route serves both the home ('liberal') and the Liberal Markets ('markets')
 * layouts, so the static route permission cannot tell them apart. The Liberal
 * Markets variant is gated by the Liberal Markets dashboard capability; the home
 * variant keeps its existing read/config permissions.
 */
final class HomeLayoutAccess {

  public function access(Request $request, AccountInterface $account): AccessResultInterface {
    if ($this->variant($request) === 'markets') {
      $result = AccessResult::allowedIfHasPermission($account, 'access liberal markets dashboard');
    }
    else {
      $permission = $request->isMethod('POST') ? 'administer site configuration' : 'access content';
      $result = AccessResult::allowedIfHasPermission($account, $permission);
    }

    return $result->addCacheContexts(['url.query_args:variant']);
  }

  private function variant(Request $request): string {
    if ($request->isMethod('POST')) {
      $payload = json_decode($request->getContent(), TRUE);

      return is_array($payload) && isset($payload['variant']) ? (string) $payload['variant'] : 'liberal';
    }

    return (string) $request->query->get('variant', 'liberal');
  }

}
