<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\unicorn_core\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class FacebookConfig {

  private ?string $appId;
  private ?string $pageId;
  private ?string $appSecret;
  private ?string $accessToken;

  public function __construct(
    private ConfigFactoryInterface $configFactory,
  ) {
    $config = $this->configFactory->get('unicorn_socials_post.settings');

    $this->appId = $config->get('facebook_app_id') ?: null;
    $this->pageId = $config->get('facebook_page_id') ?: null;
    $this->appSecret = $config->get('facebook_app_secret') ?: null;
    $this->accessToken = $config->get('facebook_access_token') ?: null;
  }

  public function getAppId(): string {
    if(!$this->appId) {
      throw new InvalidArgumentException('App id must be set.');
    }

    return $this->appId;
  }

  public function getPageId(): string {
    if(!$this->pageId) {
      throw new InvalidArgumentException('Page id must be set.');
    }

    return $this->pageId;
  }

  public function getAppSecret(): string {
    if(!$this->appSecret) {
      throw new InvalidArgumentException('App secret must be set.');
    }

    return $this->appSecret;
  }

  public function getAccessToken(): string {
    if(!$this->accessToken) {
      throw new InvalidArgumentException('Access token must be set.');
    }

    return $this->accessToken;
  }

  public function throwIfHasMissingValue(): void {

    $properties = [
      $this->appId,
      $this->pageId,
      $this->appSecret,
      $this->accessToken,
    ];

    Collection::wrap($properties)->each(function (string $property): void {
      if (!$property) {
        throw new BadRequestHttpException('Use the Admin Form of the module to set proper values');
      }
    });
  }

}
