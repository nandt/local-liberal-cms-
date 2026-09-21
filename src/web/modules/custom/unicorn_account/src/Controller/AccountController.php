<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\unicorn_account\Roles\Capability;
use Drupal\unicorn_account\Roles\Role;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AccountController implements ContainerInjectionInterface {

  private const int MIN_PASSWORD_LENGTH = 8;
  private const int MAX_DISPLAY_NAME_LENGTH = 60;

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PasswordInterface $passwordChecker,
  ) {}

  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('password'),
    );
  }

  public function me(): JsonResponse {
    $user = $this->loadCurrentUser();

    return new JsonResponse($this->profileData($user));
  }

  /**
   * Updates the logged-in user's dashboard display name.
   *
   * This deliberately does not expose Drupal's generic user JSON:API write
   * access. A dashboard user may only update their own account name.
   */
  public function updateProfile(Request $request): JsonResponse {
    $payload = json_decode($request->getContent() ?: '[]', TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Request body must be a JSON object.'], 400);
    }

    $display_name = trim((string) ($payload['display_name'] ?? ''));
    if ($display_name === '') {
      return new JsonResponse(['error' => 'display_name is required.'], 422);
    }
    if (mb_strlen($display_name) > self::MAX_DISPLAY_NAME_LENGTH) {
      return new JsonResponse(['error' => 'display_name must not exceed ' . self::MAX_DISPLAY_NAME_LENGTH . ' characters.'], 422);
    }

    $user = $this->loadCurrentUser();
    $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $display_name]);
    foreach ($accounts as $account) {
      if ((int) $account->id() !== (int) $user->id()) {
        return new JsonResponse(['error' => 'display_name is already in use.'], 422);
      }
    }

    $user->setUsername($display_name);
    $user->save();

    return new JsonResponse($this->profileData($user));
  }

  /**
   * @return array<string, mixed>
   */
  private function profileData(UserInterface $user): array {
    return [
      'uid' => (int) $user->id(),
      'uuid' => $user->uuid(),
      'name' => $user->getAccountName(),
      'display_name' => $user->getDisplayName(),
      'mail' => $user->getEmail(),
      'status' => $user->isActive() ? 'active' : 'inactive',
      'roles' => $this->roleLabels($user),
      'capabilities' => $this->capabilitiesFor($user),
    ];
  }

  /**
   * Maps the user's Liberal roles to their display labels (e.g. Editor).
   *
   * Non-Liberal roles (authenticated, legacy roles) are omitted.
   *
   * @return list<string>
   */
  private function roleLabels(UserInterface $user): array {
    $labels = [];
    foreach ($user->getRoles() as $role_id) {
      $role = Role::tryFrom($role_id);
      if ($role instanceof Role) {
        $labels[] = $role->label();
      }
    }

    return $labels;
  }

  /**
   * Τα actions που ΜΠΟΡΕΙ να κάνει ο χρήστης ανά capability.
   *
   * Επιστρέφει μόνο τα ενεργά: capability → ['view', 'create', 'update', ...].
   * Capability χωρίς κανένα granted action παραλείπεται εντελώς.
   *
   * @return array<string, list<string>>
   */
  private function capabilitiesFor(UserInterface $user): array {
    $capabilities = [];
    foreach (Capability::cases() as $capability) {
      $granted = [];
      foreach ($capability->actions() as $action => $permissions) {
        if ($permissions !== [] && array_all(
          $permissions,
          static fn (string $permission): bool => $user->hasPermission($permission),
        )) {
          $granted[] = $action;
        }
      }
      if ($granted !== []) {
        $capabilities[$capability->value] = $granted;
      }
    }

    return $capabilities;
  }

  public function changePassword(Request $request): JsonResponse {
    $payload = json_decode($request->getContent() ?: '[]', TRUE) ?? [];
    $current = (string) ($payload['current_password'] ?? '');
    $new = (string) ($payload['new_password'] ?? '');

    if ($current === '' || $new === '') {
      return new JsonResponse(['error' => 'current_password and new_password are required'], 400);
    }
    if (mb_strlen($new) < self::MIN_PASSWORD_LENGTH) {
      return new JsonResponse(['error' => 'new_password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters'], 422);
    }

    $user = $this->loadCurrentUser();
    if (!$this->passwordChecker->check($current, $user->getPassword())) {
      return new JsonResponse(['error' => 'current_password is incorrect'], 403);
    }

    $user->setPassword($new);
    $user->save();

    return new JsonResponse(['ok' => TRUE]);
  }

  private function loadCurrentUser(): UserInterface {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    assert($user instanceof UserInterface);

    return $user;
  }

}
