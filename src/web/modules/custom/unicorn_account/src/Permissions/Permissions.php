<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Permissions;

use Drupal\unicorn_core\Permissions\PermissionsInterface;
use Drupal\unicorn_core\Permissions\PermissionsTrait;

enum Permissions: string implements PermissionsInterface {
  use PermissionsTrait;

  case AccessDashboard = 'access liberal dashboard';
  case ManageOwnProfile = 'manage own liberal profile';
  case GenerateArticlePreviewLink = 'generate article preview link';
  case ViewAnyArticle = 'view any article';
  case ViewSection = 'view section';
  case CreateSection = 'create section';
  case UpdateSection = 'update section';
  case DeleteSection = 'delete section';
  case ViewOblations = 'view oblations';
  case AccessLiberalMarketsDashboard = 'access liberal markets dashboard';

  protected function getTitle(): string {
    return match ($this) {
      self::AccessDashboard => 'Access the Liberal dashboard',
      self::ManageOwnProfile => 'Manage own profile',
      self::GenerateArticlePreviewLink => 'Generate article preview link',
      self::ViewAnyArticle => 'View any article',
      self::ViewSection => 'View sections',
      self::CreateSection => 'Create sections',
      self::UpdateSection => 'Update sections',
      self::DeleteSection => 'Delete sections',
      self::ViewOblations => 'View special features',
      self::AccessLiberalMarketsDashboard => 'Access the Liberal Markets dashboard',
    };
  }

  protected function getDescription(): string {
    return match ($this) {
      self::AccessDashboard => 'Allows opening the headless dashboard and editing the home page layout.',
      self::ManageOwnProfile => 'Allows viewing and editing own profile details and changing own password from the dashboard.',
      self::GenerateArticlePreviewLink => 'Allows creating a shareable preview link for an unpublished article. Does not by itself grant access to the previewed content.',
      self::ViewAnyArticle => 'Allows viewing any article, including unpublished ones, without editing them. Used for the Commercial read-only article overview.',
      self::ViewSection => 'Allows viewing dashboard sections.',
      self::CreateSection => 'Allows creating dashboard sections.',
      self::UpdateSection => 'Allows editing dashboard sections.',
      self::DeleteSection => 'Allows deleting dashboard sections.',
      self::ViewOblations => 'Allows viewing the special features (oblations) list in the dashboard, without editing them.',
      self::AccessLiberalMarketsDashboard => 'Allows opening the Liberal Markets dashboard and managing its sections and articles.',
    };
  }

}
