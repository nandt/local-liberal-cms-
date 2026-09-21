<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Roles;

enum Role: string {

  case Administrator = 'administrator';
  case Editor = 'liberal_content_editor';
  case Commercial = 'liberal_commercial';
  case Client = 'liberal_client';

  public function label(): string {
    return match ($this) {
      self::Administrator => 'Administrator',
      self::Editor => 'Content Editor',
      self::Commercial => 'Commercial',
      self::Client => 'Client',
    };
  }

  public function isAdmin(): bool {
    return $this === self::Administrator;
  }

  /**
   * @return list<Capability|array{0: Capability, 1: list<string>}>
   */
  public function capabilities(): array {
    return match ($this) {
      self::Administrator => Capability::cases(),
      self::Editor => [
        Capability::UserProfile,
        Capability::Dashboard,
        Capability::PublishDashboard,
        Capability::LiberalMarketsDashboard,
        Capability::Articles,
        Capability::AdvertisingTools,
        Capability::Sections,
        Capability::Categories,
        Capability::Authors,
        Capability::SpecialFeatures,
        Capability::SeoMetadata,
        Capability::Settings,
        Capability::Footer,
        Capability::Sources,
        Capability::Series,
        Capability::PreviewLink,
        Capability::UnpublishedPreview,
      ],
      self::Commercial => [
        Capability::UserProfile,
        [Capability::Articles, ['view']],
        Capability::AdvertisingTools,
        Capability::PreviewLink,
        Capability::UnpublishedPreview,
      ],
      self::Client => [
        Capability::UnpublishedPreview,
      ],
    };
  }

}
