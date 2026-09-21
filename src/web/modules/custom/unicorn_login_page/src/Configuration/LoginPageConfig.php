<?php

declare(strict_types=1);

namespace Drupal\unicorn_login_page\Configuration;

use Drupal\unicorn_core\Support\Collection;

class LoginPageConfig
{
  private const string THEME = 'unicorn_basic_theme';
  private const string ICON_DIRECTORY = 'images/page/login';
  private const string LOCK_ICON = 'lock.svg';
  private const string EYE_ICON = 'eye.svg';
  private const string EYE_SLASH_ICON = 'eye-slash.svg';
  private const string ALERT_ICON = 'alert.svg';
  private const string STATUS_ICON = 'status.svg';

  private const array AUTO_CLONED_BLOCKS = [
    'unicorn_basic_theme_breadcrumbs',
    'unicorn_basic_theme_content',
    'unicorn_basic_theme_local_actions',
    'unicorn_basic_theme_messages',
    'unicorn_basic_theme_page_title',
    'unicorn_basic_theme_primary_local_tasks',
    'unicorn_basic_theme_secondary_local_tasks',
  ];

  public function getTheme(): string {
    return self::THEME;
  }

  /**
   * @return Collection<int, string>
   */
  public function getAutoClonedBlocks(): Collection {
    return Collection::wrap(self::AUTO_CLONED_BLOCKS);
  }

  public function getLoginLibrary(): string {
    return self::THEME . '/login';
  }

  public function getIconDirectory(): string {
    return self::ICON_DIRECTORY;
  }

  public function getLockIcon(): string {
    return self::LOCK_ICON;
  }

  public function getEyeIcon(): string {
    return self::EYE_ICON;
  }

  public function getEyeSlashIcon(): string {
    return self::EYE_SLASH_ICON;
  }

  public function getAlertIcon(): string {
    return self::ALERT_ICON;
  }

  public function getStatusIcon(): string {
    return self::STATUS_ICON;
  }
}
