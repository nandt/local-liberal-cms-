<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\unicorn_core\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class XConfig {

  private readonly ?string $consumerKey;
  private readonly ?string $consumerSecret;
  private readonly ?string $accessToken;
  private readonly ?string $accessTokenSecret;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $config = $this->configFactory->get('unicorn_socials_post.settings');


    $this->consumerKey = $config->get('x_consumer_key') ?: null;
    $this->consumerSecret = $config->get('x_consumer_secret') ?: null;
    $this->accessToken = $config->get('x_access_token') ?: null;
    $this->accessTokenSecret = $config->get('x_access_token_secret') ?: null;
  }

  public function getConsumerKey(): string {
    if(!$this->consumerKey) {
      throw new InvalidArgumentException('Consumer key must be set.');
    }

    return $this->consumerKey;
  }

  public function getConsumerSecret(): string {
    if(!$this->consumerSecret) {
      throw new InvalidArgumentException('Consumer secret must be set.');
    }

    return $this->consumerSecret;
  }

  public function getAccessToken(): string {
    if(!$this->accessToken) {
      throw new InvalidArgumentException('Access token must be set.');
    }

    return $this->accessToken;
  }

  public function getAccessTokenSecret(): string {
    if(!$this->accessTokenSecret) {
      throw new InvalidArgumentException('Access token secret must be set.');
    }

    return $this->accessTokenSecret;
  }

  public function throwIfHasMissingValue(): void {

    $properties = [
      $this->consumerKey,
      $this->consumerSecret,
      $this->accessToken,
      $this->accessTokenSecret
    ];

    Collection::wrap($properties)->each(function (string $property): void {
      if (!$property) {
        throw new BadRequestHttpException('Use the Admin Form of the module to set proper values');
      }
    });

  }

}
