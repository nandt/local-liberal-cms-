<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class SessionController extends ControllerBase {

  public function __construct(protected SessionManagerInterface $sessionManager) {}

  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self($container->get('session_manager'));
  }

  public function destroy(): JsonResponse {
    $revoked = 0;
    $account = $this->currentUser();
    if ($account->isAuthenticated()) {
      $storage = $this->entityTypeManager()->getStorage('oauth2_token');
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('auth_user_id', $account->id())
        ->execute();
      if ($ids) {
        $storage->delete($storage->loadMultiple($ids));
        $revoked = count($ids);
      }
      $this->sessionManager->delete($account->id());
    }

    $this->sessionManager->destroy();
    return new JsonResponse(['ok' => TRUE, 'revoked' => $revoked]);
  }

}
