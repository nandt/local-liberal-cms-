<?php

declare(strict_types=1);

namespace Drupal\unicorn_promotional_banner\Configuration;

class PromotionalBannerConfig
{
  private const string DIRECTORY = 'promotional_banner';

  private const string STEAM_WRAPPER = 'public://';

  private const int IMAGE_SCALE_WIDTH = 196;

  private const array ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

  private const int MAX_FILE_UPLOAD = 20971520;

  public function getDirectory(): string {
    return self::DIRECTORY;
  }

  public function getSteamWrapper(): string {
    return self::STEAM_WRAPPER;
  }

  public function getImageScaleWidth(): int {
    return self::IMAGE_SCALE_WIDTH;
  }

  /**
   * @return list<string>
   */
  public function getAllowedExtensions(): array {
    return self::ALLOWED_EXTENSIONS;
  }

  public function getAllowedExtensionsString(): string {
    return implode(' ', self::ALLOWED_EXTENSIONS);
  }

  public function getMaxFileUpload(): int {
    return self::MAX_FILE_UPLOAD;
  }
}
