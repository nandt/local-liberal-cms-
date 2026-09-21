<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_socials_post\Cache\SocialsPostFieldCache;
use Drupal\unicorn_socials_post\Plugin\Field\SocialsPostField;

/**
 * @phpstan-consistent-constructor
 */
readonly class UnicornSocialsPostHooks {

  public function __construct(
    private Connection $connection,
    private RouteMatchInterface $routeMatch,
  ) {}

  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    $this->connection->delete('unicorn_social_sharing')
      ->condition('entity_id', $node->id())
      ->execute();
  }

  /**
   *  Pre-emptively load the social media information of the article entities
   *  Take care here, we load it in every jsonapi route to avoid N+1 inside
   *  the computed field.
   *
   * @param array<int|string, EntityInterface> $entities
   */
  #[Hook('entity_load')]
  public function entityLoad(array $entities, string $entity_type_id): void {
    if ($entity_type_id !== 'node') {
      return;
    }

    // dont run queries if we are not in jsonapi routes
    $routeName = $this->routeMatch->getRouteName() ?? '';
    if (!str_starts_with($routeName, 'jsonapi.')) {
      return;
    }

    $entitiesCollection = Collection::wrap($entities);
    $articlesCollection = $entitiesCollection->filter(static fn (EntityInterface $entity): bool => $entity->bundle() === 'article_liberal');

    if ($articlesCollection->isEmpty()) {
      return;
    }

    $articlesCollectionIds = array_values(
      $articlesCollection->map(static fn (EntityInterface $article): int => (int) $article->id())->toArray()
    );

    SocialsPostFieldCache::preload(
      $articlesCollectionIds,
      $this->connection
    );
  }

  /**
   * @param array<string, FieldDefinitionInterface> $base_field_definitions
   * @return array<string, BaseFieldDefinition>
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, string $bundle, array $base_field_definitions): array {
    if ($bundle !== 'article_liberal' || $entity_type->id() !== 'node') {
      return [];
    }

    $fields['socials_post'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Socials Post'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      ->setClass(SocialsPostField::class);

    return $fields;
  }

}
