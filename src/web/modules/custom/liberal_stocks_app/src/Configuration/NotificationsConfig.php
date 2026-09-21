<?php

declare(strict_types=1);

namespace Drupal\liberal_stocks_app\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;
use InvalidArgumentException;

class NotificationsConfig
{

  private const string STOCKS_LOGIN_ENDPOINT = "/app/liberal-stocks/users/login";

  private const string STOCKS_NOTIFICATIONS_ENDPOINT = "/app/liberal-stocks/important-news";

  private readonly ?string $username;
  private readonly ?string $password;

  private readonly string $host;
  private string $accessToken;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $host = getenv('STOCKS_APP_HOST_URL');

    if (!$host) {
      throw new InvalidArgumentException(
        'STOCKS_APP_HOST_URL environment variable must be set.'
      );
    }

    $this->host = $host;
    $config = $this->configFactory->get('liberal_stocks_app.settings');

    $this->username = $config->get('username') ?: null;
    $this->password = $config->get('password') ?: null;
  }

  public function getUsername(): string {
    if(!$this->username) {
      throw new InvalidArgumentException('Username must be set.');
    }

    return $this->username;
  }

  public function getPassword(): string {
    if(!$this->password) {
      throw new InvalidArgumentException('Password must be set.');
    }

    return $this->password;
  }

  public function getLoginEndpoint(): string {
    return $this->host . self::STOCKS_LOGIN_ENDPOINT;
  }

  public function getNotificationsEndpoint(): string {
    return $this->host . self::STOCKS_NOTIFICATIONS_ENDPOINT;
  }

  public function getAccessToken(): string {
    return $this->accessToken;
  }

  public function setAccessToken(string $accessToken): self {
    $this->accessToken = $accessToken;

    return $this;
  }

  public function hasMissingValue(): bool {
    if(!$this->username) {
      return true;
    }

    if(!$this->password) {
      return true;
    }

    return false;
  }
}
