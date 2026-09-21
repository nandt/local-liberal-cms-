<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\unicorn_advertising_tools\Support\AdvertisingToolsUtils;
use Drupal\node\NodeInterface;

final readonly class AdvertisingToolsHooks {

  private EntityStorageInterface $nodeStorage;

  public function __construct(
    private Connection $connection,
    private EntityTypeManagerInterface $entityTypeManager,
    private TimeInterface $time,
    private AdvertisingToolsUtils $advertisingToolsUtils

  ) {
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entityType, string $bundle): void {
    if ($entityType->id() !== 'node' || $bundle !== 'article_liberal') {
      return;
    }

    if (isset($fields['field_kodikas_impression_image'])) {
      $fields['field_kodikas_impression_image']->addConstraint('ValidAdvertisingImageSnippet');
    }
  }

  #[Hook('node_delete')]
  public function syncAdvertisingToolsRecordDelete(NodeInterface $node): void
  {
    if($node->getType() === "page") {
      return;
    }

      // the return value is the number of rows affected
      $result = $this->connection->delete('unicorn_advertising_tools')
        ->condition('entity_id', $node->id())
        ->execute();

      if ($result > 0) {
        $this->advertisingToolsUtils->clearAdToolsCache(true);
      }
  }

  #[Hook('node_delete')]
  public function deleteReadMoreIfNodeDeleted(NodeInterface $node): void {

    if($node->getType() === "page") {
      return;
    }

    $query = $this->connection->select('node__field_homepage_bullets', 'nhb')
      ->fields('nhb', ['entity_id', 'delta'])
      ->condition('bundle', 'article_liberal')
      ->condition('field_homepage_bullets_target_id', $node->id());

    $fix_leftovers = $query->execute()?->fetchAllAssoc(
      'entity_id',
      FetchAs::Associative
    );

    if (!empty($fix_leftovers)) {
      $fix_nodes = $this->nodeStorage->loadMultiple(array_keys($fix_leftovers));

      foreach ($fix_leftovers as $key => $fix_leftover) {
        $bullets = $fix_nodes[$key]->get('field_homepage_bullets');
        $bullets->removeItem($fix_leftover['delta']);
        $fix_nodes[$key]->save();
      }
    }
  }

  #[Hook('node_update')]
  public function removeFromPromotedAndReadMoreOnUpdate(NodeInterface $node): void
  {
    if ($node->getOriginal()?->isPublished() && !$node->isPublished() && $node->bundle() === 'article_liberal') {
      $promoted_articles = $this->advertisingToolsUtils->fetchPromotedArticles();

      // Find in which nodes it's connected (if any) as Read More and remove it
      $query = $this->connection->select('node__field_homepage_bullets', 'nhb')
        ->fields('nhb', ['entity_id', 'delta'])
        ->condition('bundle', 'article_liberal')
        ->condition('field_homepage_bullets_target_id', $node->id());

      $remove_homepage_bullets = $query->execute()?->fetchAllAssoc(
        'entity_id',
        FetchAs::Associative
      );

      if (!empty($remove_homepage_bullets)) {
        $remove_nodes = $this->nodeStorage->loadMultiple(
          array_keys($remove_homepage_bullets)
        );

        // remove all read more items
        foreach ($remove_homepage_bullets as $key => $remove_homepage_bullet) {
          $bullets = $remove_nodes[$key]->get('field_homepage_bullets');
          $bullets->removeItem($remove_homepage_bullet['delta']);
          $remove_nodes[$key]->save();
        }
      }

      // But it's also in the promoted list, then move to history
      if ($promoted_articles->containsKey((int) $node->id())) {
        $query = $this->connection->update('unicorn_advertising_tools')
          ->fields(['state' => 0, 'history_date' => $this->time->getRequestTime()])
          ->condition('entity_id', $node->id());

        $query->execute();
        $this->advertisingToolsUtils->clearAdToolsCache(true);
      }
    }
  }

}
