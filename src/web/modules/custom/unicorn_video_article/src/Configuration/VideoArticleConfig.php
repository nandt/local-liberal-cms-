<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;
use InvalidArgumentException;

final readonly class VideoArticleConfig {

  private const string STREAM_API_URL = 'https://api.cloudflare.com/client/v4/accounts/0f1a930b16ca04f07cb2e429e3ffd70e/stream/';
  private const array VIDEO_TERM_NAMES = [
    'Video Article',
    'Βίντεο',
  ];
  private const string BUNDLE = 'article_liberal';
  private ?string $authKey;


  public function __construct(
    private ConfigFactoryInterface $configFactory,
  ) {
    $config = $this->configFactory->get('unicorn_video_article.settings');

    $this->authKey = $config->get('api_key_account_cf') ?: null;
  }

  public function getStreamApiUrl(): string {
    return self::STREAM_API_URL;
  }

  public function getAuthKey(): string {
    if(!$this->authKey) {
      throw new InvalidArgumentException('Missing authorization key.');
    }

    return $this->authKey;
  }

  /**
   * @return string[]
   */
  public function getTermNames(): array {
    return self::VIDEO_TERM_NAMES;
  }

  public function getBundle(): string {
    return self::BUNDLE;
  }

}
