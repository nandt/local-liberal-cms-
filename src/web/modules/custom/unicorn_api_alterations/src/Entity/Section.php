<?php

namespace Drupal\unicorn_api_alterations\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\unicorn_api_alterations\Controller\SectionTemplatesController;

/**
 * Section content entity — επαναχρησιμοποιήσιμο building block της αρχικής.
 *
 * Κάθε section = ένα template (σταθερά article slots) + metadata (internal name,
 * display name, link, feature). Η τοποθέτησή τους στην αρχική (liberal/markets)
 * κρατιέται ξεχωριστά ως State blob μέσω του HomePageLayoutController.
 *
 * @ContentEntityType(
 *   id = "section",
 *   label = @Translation("Section"),
 *   base_table = "section",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "internal_name",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   admin_permission = "administer taxonomy",
 * )
 */
class Section extends ContentEntityBase {

  #[\Override]
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['internal_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Internal name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 50)
      ->addConstraint('UniqueField', [
        'message' => 'This internal name already exists.',
      ]);

    $fields['display_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Display name'))
      ->setSetting('max_length', 50);

    $fields['template'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Template'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', array_column(
        SectionTemplatesController::TEMPLATES,
        'label',
        'id',
      ));

    $fields['link'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Link'))
      ->setSetting('max_length', 255);

    // Ad/feature image — δύο modes (όπως στο Figma "Upload image / Image Snippet"):
    //   image         → upload αρχείου (file reference)
    //   image_snippet → HTML/embed snippet (εναλλακτικά του upload)
    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('Ad/feature image (upload).'))
      ->setSettings([
        'file_directory' => 'section-images/[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'png jpg jpeg webp',
        'max_filesize' => '4 MB',
        'alt_field' => TRUE,
        'alt_field_required' => FALSE,
      ]);

    $fields['image_snippet'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Image snippet'))
      ->setDescription(t('HTML/embed snippet — εναλλακτικά του image upload.'))
      ->addConstraint('ValidAdvertisingSectionImageSnippet');

    $fields['feature'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Feature'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['oblations' => 'oblations']]);

    $fields['article_slot'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Article slot'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

}
