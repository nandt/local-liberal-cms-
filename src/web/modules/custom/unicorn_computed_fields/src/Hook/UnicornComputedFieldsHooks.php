<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\unicorn_computed_fields\Plugin\Field\ArticleStatusField;
use Drupal\unicorn_computed_fields\Plugin\Field\CanBeDeletedField;
use Drupal\unicorn_computed_fields\Plugin\Field\FeatureArticleSlotsField;
use Drupal\unicorn_computed_fields\Plugin\Field\FeatureLegacyUrlField;
use Drupal\unicorn_computed_fields\Plugin\Field\FeaturePageLayoutLabelField;
use Drupal\unicorn_computed_fields\Plugin\Field\SectionTemplateLabelField;
use Drupal\unicorn_computed_fields\Plugin\Field\TermCreatedField;

/**
 * Defines computed fields supplied by the module.
 */
final readonly class UnicornComputedFieldsHooks {

  private const array CREATED_TERM_BUNDLES = ['oblations', 'series'];

  /**
   * Bundles whose listing shows a Delete action: US 5.4, 7.4, 12.5, 13.4, 13.8.
   */
  private const array DELETABLE_TERM_BUNDLES = ['oblations', 'series', 'category', 'arthrografos', 'piges_arthron'];

  /**
   * @param array<string, FieldDefinitionInterface> $base_field_definitions
   *
   * @return array<string, BaseFieldDefinition>
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, string $bundle, array $base_field_definitions): array {
    $fields = [];

    if ($entity_type->id() === 'node' && $bundle === 'article_liberal') {
      $fields['article_status'] = BaseFieldDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Article status'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(ArticleStatusField::class);
    }

    if ($entity_type->id() === 'section') {
      $fields['template_label'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Template label'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(SectionTemplateLabelField::class);
    }

    if ($entity_type->id() === 'taxonomy_term' && $bundle === 'oblations') {
      $fields['article_slots'] = BaseFieldDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Article slots'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(FeatureArticleSlotsField::class);

      $fields['page_layout_label'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Page layout label'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(FeaturePageLayoutLabelField::class);

      $fields['legacy_url'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Legacy URL'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(FeatureLegacyUrlField::class);
    }

    if ($entity_type->id() === 'taxonomy_term' && in_array($bundle, self::CREATED_TERM_BUNDLES, TRUE)) {
      $fields['created'] = BaseFieldDefinition::create('timestamp')
        ->setLabel(new TranslatableMarkup('Created'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(TermCreatedField::class);
    }

    if ($this->supportsDeletionFlag($entity_type->id(), $bundle)) {
      $fields['can_be_deleted'] = BaseFieldDefinition::create('boolean')
        ->setLabel(new TranslatableMarkup('Can be deleted'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(CanBeDeletedField::class);
    }

    return $fields;
  }

  private function supportsDeletionFlag(string $entity_type_id, string $bundle): bool {
    if ($entity_type_id === 'section') {
      return TRUE;
    }

    return $entity_type_id === 'taxonomy_term' && in_array($bundle, self::DELETABLE_TERM_BUNDLES, TRUE);
  }

}
