<?php

declare(strict_types=1);

namespace Drupal\unicorn_login_page\Hook;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\unicorn_login_page\Configuration\LoginPageConfig;
use Drupal\user\UserAuthenticationInterface;

final readonly class LoginFormHooks {

  private const string INVALID_CREDENTIALS_MESSAGE = 'The email or password you entered is incorrect.';
  private const string BLOCKED_ACCOUNT_MESSAGE = 'Your account has been disabled. Please contact an administrator for assistance.';

  public function __construct(
    private RouteMatchInterface $routeMatch,
    private ThemeExtensionList $themeExtensionList,
    private LoginPageConfig $loginPageConfig,
    private RendererInterface $renderer,
    private MessengerInterface $messenger,
    private UserAuthenticationInterface $userAuthentication,
  ) {}

  /**
   * @param array<string, mixed> $variables
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if ($this->routeMatch->getRouteName() === 'user.login') {
      $variables['attributes']['class'][] = 'unicorn-login-page';
    }
  }

  /**
   * @param array<string, mixed> $form
   */
  #[Hook('form_user_login_form_alter')]
  public function formUserLoginFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['name']['#title'] = 'Email address';
    $form['name']['#attributes']['placeholder'] = 'you@example.com';
    $form['name']['#element_validate'][] = $this->captureLoginIdentifier(...);
    unset($form['name']['#description']);

    $form['pass']['#title'] = 'Password';
    $form['pass']['#attributes']['placeholder'] = 'Password';
    $form['pass']['#attributes']['class'][] = 'unicorn-login-password';
    $form['pass']['#field_prefix'] = $this->lockIcon();
    $form['pass']['#field_suffix'] = $this->passwordToggleButton();
    unset($form['pass']['#description']);

    $form['actions']['submit']['#value'] = 'Log in';
    $form['messages'] = [
      '#pre_render' => [$this->renderMessages(...)],
      '#weight' => -100,
    ];

    $form['#attached']['library'][] = $this->loginPageConfig->getLoginLibrary();
    $form['#prefix'] = $this->loginFormOpen();
    $form['#suffix'] = $this->loginFormClose();

    $form['#validate'][] = $this->normalizeLoginErrors(...);
  }

  /**
   * Preserves the submitted email before Mail Login normalizes it to a username.
   *
   * @param array<string, mixed> $element
   */
  public function captureLoginIdentifier(array &$element, FormStateInterface $form_state): void {
    $form_state->set('unicorn_login_page.login_identifier', trim((string) $element['#value']));
  }

  /**
   * @param array<string, mixed> $form
   */
  private function normalizeLoginErrors(array &$form, FormStateInterface $form_state): void {
    $errors = $form_state->getErrors();
    if ($errors === []) {
      return;
    }

    $form_state->clearErrors();

    $message = $this->isBlockedAccountLogin($form_state)
      ? self::BLOCKED_ACCOUNT_MESSAGE
      : self::INVALID_CREDENTIALS_MESSAGE;

    $form_state->setErrorByName('name', $message);
  }

  private function isBlockedAccountLogin(FormStateInterface $form_state): bool {
    $identifier = (string) $form_state->get('unicorn_login_page.login_identifier');
    $password = trim((string) $form_state->getValue('pass'));
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL) || $password === '') {
      return FALSE;
    }

    $account = $this->userAuthentication->lookupAccount($identifier);

    return $account !== FALSE && $account->isBlocked();
  }

  /**
   * @param array<string, mixed> $element
   *
   * @return array<string, mixed>
   */
  private function renderMessages(array $element): array {
    /** @var array<string, array<int, string|MarkupInterface>> $messages */
    $messages = $this->messenger->deleteAll();
    if ($messages === []) {
      return $element;
    }

    $banners = '';
    foreach ($messages as $type => $typeMessages) {
      $iconFile = $type === 'status' ? $this->loginPageConfig->getStatusIcon() : $this->loginPageConfig->getAlertIcon();
      $role = $type === 'status' ? 'status' : 'alert';

      foreach ($typeMessages as $message) {
        $icon = $this->icon($iconFile, ['unicorn-login__message-icon']);

        $banners .= '<div class="unicorn-login__message unicorn-login__message--' . $type . '" role="' . $role . '">'
          . $this->renderer->renderInIsolation($icon)
          . '<span>' . Html::escape((string) $message) . '</span>'
          . '</div>';
      }
    }

    $element['#markup'] = Markup::create($banners);

    return $element;
  }

  private function passwordToggleButton(): MarkupInterface {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#attributes' => [
        'type' => 'button',
        'class' => ['unicorn-login-toggle-password'],
        'aria-label' => 'Show password',
        'aria-pressed' => 'false',
      ],
      'hidden_icon' => $this->icon(
        $this->loginPageConfig->getEyeSlashIcon(),
        ['unicorn-login-toggle-password__icon', 'unicorn-login-toggle-password__icon--hidden-state'],
      ),
      'visible_icon' => $this->icon(
        $this->loginPageConfig->getEyeIcon(),
        ['unicorn-login-toggle-password__icon', 'unicorn-login-toggle-password__icon--visible-state'],
      ),
    ];

    return $this->renderer->renderInIsolation($build);
  }

  private function lockIcon(): MarkupInterface {
    $build = $this->icon($this->loginPageConfig->getLockIcon(), ['unicorn-login-field-icon']);

    return $this->renderer->renderInIsolation($build);
  }

  /**
   * @param list<string> $classes
   *
   * @return array<string, mixed>
   */
  private function icon(string $file, array $classes): array {
    return [
      '#theme' => 'image',
      '#uri' => $this->imagePath($file),
      '#alt' => '',
      '#attributes' => ['class' => $classes],
    ];
  }

  private function imagePath(string $file): string {
    return $this->themeExtensionList->getPath($this->loginPageConfig->getTheme())
      . '/' . $this->loginPageConfig->getIconDirectory()
      . '/' . $file;
  }

  private function loginFormOpen(): MarkupInterface {
    $logo = Html::escape(base_path() . $this->themeExtensionList->getPath($this->loginPageConfig->getTheme()) . '/img/liberal_logo.svg');
    $title = 'User Login';
    $subtitle = 'Please enter your credentials to log in.';

    $markup = Markup::create(<<<HTML
    <div class="unicorn-login">
      <header class="unicorn-login__topbar">
        <img src="{$logo}" alt="Liberal" class="unicorn-login__topbar-logo">
      </header>
      <main class="unicorn-login__main">
        <div class="unicorn-login__card">
          <img src="{$logo}" alt="Liberal" class="unicorn-login__card-logo">
          <h1 class="unicorn-login__title">{$title}</h1>
          <p class="unicorn-login__subtitle">{$subtitle}</p>
    HTML);
    assert($markup instanceof MarkupInterface);

    return $markup;
  }

  private function loginFormClose(): MarkupInterface {
    $markup = Markup::create("</div>\n</main>\n</div>");
    assert($markup instanceof MarkupInterface);

    return $markup;
  }

}
