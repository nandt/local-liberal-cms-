<?php

declare(strict_types=1);

namespace Drupal\unicorn_account\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\JsonApiFilter;
use Drupal\unicorn_account\Access\SectionAccess;

/**
 * Hook implementations for section access.
 */
final readonly class SectionHooks {

  public function __construct(
    private SectionAccess $sectionAccess,
  ) {}

  /**
   * Controls access to an existing section entity.
   */
  #[Hook('section_access')]
  public function sectionAccess(EntityInterface $entity, string $op, AccountInterface $account): AccessResultInterface {
    return $this->sectionAccess->access($op, $account);
  }

  /**
   * Controls access to create a section entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which access is checked.
   * @param array<string, mixed> $context
   *   The entity creation context.
   * @param string|null $entity_bundle
   *   The section bundle, when available.
   */
  #[Hook('section_create_access')]
  public function sectionCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle): AccessResultInterface {
    return $this->sectionAccess->createAccess($account);
  }

  /**
   * Allows users who can view sections to filter the JSON:API collection.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The section entity type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which filter access is checked.
   *
   * @return array<string, \Drupal\Core\Access\AccessResultInterface>
   *   Filter access results keyed by the JSON:API entity subset.
   */
  #[Hook('jsonapi_section_filter_access')]
  public function sectionFilterAccess(EntityTypeInterface $entity_type, AccountInterface $account): array {
    return [
      JsonApiFilter::AMONG_ALL => $this->sectionAccess->access('view', $account),
    ];
  }

}
