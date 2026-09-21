<?php

declare(strict_types=1);

namespace Drupal\liberal_podcasts\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\PathAliasInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;

/**
 * One-off Drush migration: Podcast nodes → article_liberal nodes.
 *
 * Usage:
 *   drush liberal_podcasts:migrate # run the migration
 *   drush liberal_podcasts:migrate --update # re-process already-migrated nodes
 *   drush liberal_podcasts:migrate --rollback # delete all migrated article_liberal nodes
 *   drush liberal_podcasts:migrate --limit=10 # process only N podcasts (for testing)
 *
 * Duplicate prevention: checks migration map table before creating.
 * Re-run safe: skips already-migrated nodes unless --update is passed.
 * Rollback: deletes only nodes recorded in the migration map for this migration.
 */
class PodcastMigrationCommands extends DrushCommands {

  use AutowireTrait;

  private const int CHUNK = 50;

  private const string SOURCE_BUNDLE = 'podcast';
  private const string DEST_BUNDLE = 'article_liberal';

  /**
   * Tracks source→dest nid pairs created by this migration. Used for rollback.
   */
  private const string MAP_TABLE = 'liberal_podcasts_migration_map';


  /**
   * Fields renamed on the destination bundle. Key = source machine name on
   * podcast, value = destination machine name on article_liberal.
   */
  private const array RENAMED_FIELDS = [
    'field_podcast_source_code' => 'field_vid_article_source_code',
    'field_podcast_series' => 'field_vid_article_series',
    'field_podcast_episode' => 'field_vid_article_episode',
    'field_podcast_duration' => 'field_vid_article_duration',
    'field_podcast_width' => 'field_vid_article_width',
    'field_podcast_height' => 'field_vid_article_height',
  ];

  /**
   * Fields that exist on both bundles under the same machine name and can be
   * copied verbatim via ->getValue() / ->set(). Empty source fields are skipped
   * so empty strings never overwrite meaningful destination values on --update.
   */
  private const array DIRECT_FIELDS = [
    // Editorial taxonomy
    'field_liberal_category',
    'field_liberal_tags',
    'field_article_region',
    'field_oblation_category',
    'field_arthrografos',
    'field_eidos_arthrou',
    'field_metohi',
    'field_pigi_arthroy',
    // Images
    'field_kentriki_fotografia',
    'field_lezanta_fotografias',
    'field_pigi_fotografias',
    'field_impression_image_url',
    'field_kodikas_impression_image',
    // Editorial flags
    'field_block_flag',
    'field_no_advertisements',
    'field_emfanisi_arthrografoy_stin',
    'field_energos_ypertitlos',
    // Subtitle
    'field_subtitle',
    // Date
    'field_teleytaia_enimerosi',
    // Legacy social/notification counters
    'field_notification_liberal_count',
    'field_notification_liberal_time',
    'field_post_fb_liberal_counter',
    'field_post_fb_liberal_latest_tim',
    'field_post_twitter_liberal_count',
    'field_post_twitter_liberal_lates',
    // Placement weights
    'field_additional_articles_weight',
    'field_afieroma2_weight',
    'field_analysts_weight',
    'field_blackbox_weight',
    'field_business_home_weight',
    'field_car_weight',
    'field_culture_and_arts_weight',
    'field_economy_weight',
    'field_featured_weight',
    'field_home_weight',
    'field_international_weight',
    'field_lm_main_articles_weight',
    'field_lm_top_stories_weight',
    'field_oblation_2_articles_weight',
    'field_oblation_3_articles_weight',
    'field_oblation_articles_weight',
    'field_oblation_main_article_weig',
    'field_oblation_zone_a_weight',
    'field_oblation_zone_b_weight',
    'field_politic_weight',
    'field_stoixima_weight',
    'field_technology_weight',
    'field_top_stories_weight',
    'field_views_weight',
    'field_weight',
    'field_z1_blackbox_weight',
    'field_z1_bottom_weight',
    'field_z1_home_weight',
    'field_z1_top_weight',
    'field_z2_bottom_weight',
    'field_z2_middle_weight',
    'field_z2_top_weight',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct();
  }

  /**
   * @param array<string, mixed> $options
   */
  #[CLI\Command(name: 'liberal_podcasts:migrate')]
  #[CLI\Option(name: 'update', description: 'Re-process and overwrite already-migrated nodes')]
  #[CLI\Option(name: 'rollback', description: 'Delete all article_liberal nodes previously migrated from podcasts')]
  #[CLI\Option(name: 'limit', description: 'Process at most N podcast nodes (useful for smoke-testing)')]
  public function migrate(array $options = [
    'update' => FALSE,
    'rollback' => FALSE,
    'limit' => 0,
  ]): int {
    if ($options['rollback']) {
      return $this->doRollback();
    }

    return $this->doMigrate(
      update: (bool) $options['update'],
      limit: (int) $options['limit'],
    );
  }

  /**
   * Deletes all Podcast nodes, field instances, field storages, and the Podcast
   * content type. Run this ONLY after the migration has been verified.
   *
   * Deleting the content type cascades to all field instances on the podcast
   * bundle. Field storages that are only used by podcast are also removed.
   *
   * Usage:
   *   drush liberal_podcasts:cleanup
   */
  #[CLI\Command(name: 'liberal_podcasts:cleanup')]
  public function cleanup(): int {
    if (!$this->io()->confirm('This will permanently delete ALL podcast nodes, the podcast content type, and its field storages. Are you sure?')) {
      $this->logger()?->notice('Cleanup aborted.');
      return Command::SUCCESS;
    }

    return $this->doCleanup();
  }

  private function doCleanup(): int {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // Step 1: delete all podcast nodes in chunks.
    $lastNid = 0;
    $deleted = 0;

    while (TRUE) {
      $nids = $this->database->select('node_field_data', 'nfd')
        ->fields('nfd', ['nid'])
        ->condition('nfd.type', self::SOURCE_BUNDLE)
        ->condition('nfd.nid', $lastNid, '>')
        ->orderBy('nfd.nid')
        ->range(0, self::CHUNK)
        ->execute()
        ?->fetchCol();

      if (!$nids) {
        break;
      }

      $lastNid = (int) max($nids);
      $nodes = $nodeStorage->loadMultiple($nids);
      $nodeStorage->delete($nodes);
      $deleted += count($nids);
      $nodeStorage->resetCache();
    }

    $this->logger()?->notice('Deleted {count} podcast nodes.', ['count' => $deleted]);

    // Step 2: delete the content type — cascades field instances and orphaned
    // field storages that are only attached to the podcast bundle.
    $nodeType = $this->entityTypeManager
      ->getStorage('node_type')
      ->load(self::SOURCE_BUNDLE);

    if ($nodeType === NULL) {
      $this->logger()?->warning('Content type "{bundle}" not found — already deleted?', ['bundle' => self::SOURCE_BUNDLE]);
      return Command::SUCCESS;
    }

    $nodeType->delete();

    // Drop the migration map table — no longer needed after cleanup.
    if ($this->database->schema()->tableExists(self::MAP_TABLE)) {
      $this->database->schema()->dropTable(self::MAP_TABLE);
      $this->logger()?->notice('Migration map table dropped.');
    }

    $this->logger()?->notice('Content type "{bundle}" deleted. Cleanup complete.', ['bundle' => self::SOURCE_BUNDLE]);
    return Command::SUCCESS;
  }

  private function doRollback(): int {
    $this->ensureMapTable();
    $storage = $this->entityTypeManager->getStorage('node');

    $destNids = $this->database->select(self::MAP_TABLE, 'm')
      ->fields('m', ['dest_nid'])
      ->execute()
      ?->fetchCol();

    if (empty($destNids)) {
      $this->logger()?->notice('Migration map is empty — nothing to roll back.');
      return Command::SUCCESS;
    }

    $this->logger()?->notice(
      '{count} mapped nodes found. Deleting…',
      ['count' => count($destNids)]
    );

    foreach (array_chunk($destNids, self::CHUNK) as $chunk) {
      $nodes = $storage->loadMultiple($chunk);
      if ($nodes) {
        $storage->delete($nodes);
      }
    }

    $this->database->truncate(self::MAP_TABLE)->execute();

    $this->logger()?->notice('Rollback complete. Deleted {count} nodes.', ['count' => count($destNids)]);
    return Command::SUCCESS;
  }

  private function doMigrate(bool $update, int $limit): int {
    $this->ensureMapTable();

    if (!$this->preflight()) {
      return Command::FAILURE;
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $aliasStorage = $this->entityTypeManager->getStorage('path_alias');
    $podcastTermId = $this->ensurePodcastTerm();

    $processed = $created = $updated = $skipped = $failed = 0;
    $lastNid = 0;

    while (TRUE) {
      $chunkSize = ($limit > 0) ? min(self::CHUNK, $limit - $processed) : self::CHUNK;
      if ($chunkSize <= 0) {
        break;
      }

      $nids = $this->database->select('node_field_data', 'nfd')
        ->fields('nfd', ['nid'])
        ->condition('nfd.type', self::SOURCE_BUNDLE)
        ->condition('nfd.nid', $lastNid, '>')
        ->orderBy('nfd.nid')
        ->range(0, $chunkSize)
        ->execute()
        ?->fetchCol();

      if (!$nids) {
        break;
      }

      $lastNid = (int) max($nids);
      $sources = $nodeStorage->loadMultiple($nids);

      foreach ($sources as $source) {
        $processed++;
        try {
          $outcome = $this->processNode($source, $nodeStorage, $aliasStorage, $podcastTermId, $update);
          match ($outcome) {
            'created' => $created++,
            'updated' => $updated++,
            'skipped' => $skipped++,
          };
        }
        catch (\Throwable $e) {
          $failed++;
          $this->logger()?->error(
            'Failed on podcast nid={nid}: {message}',
            ['nid' => $source->id(), 'message' => $e->getMessage()]
          );
        }
      }

      $nodeStorage->resetCache();
    }

    $this->logger()?->notice(
      'Done. processed={p} created={c} updated={u} skipped={s} failed={f}',
      ['p' => $processed, 'c' => $created, 'u' => $updated, 's' => $skipped, 'f' => $failed]
    );

    return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
  }

  private function processNode(
    NodeInterface $source,
    EntityStorageInterface $nodeStorage,
    EntityStorageInterface $aliasStorage,
    int $podcastTermId,
    bool $update,
  ): string {
    $sourceNid = (int) $source->id();

    // Duplicate check via migration map
    $existingDestNid = $this->database->select(self::MAP_TABLE, 'm')
      ->fields('m', ['dest_nid'])
      ->condition('m.source_nid', $sourceNid)
      ->execute()
      ?->fetchField();

    if ($existingDestNid !== FALSE) {
      if (!$update) {
        return 'skipped';
      }
      $dest = $nodeStorage->load((int) $existingDestNid);
      if (!$dest instanceof NodeInterface) {
        // Map entry points to a deleted node — treat as new.
        $dest = $nodeStorage->create(['type' => self::DEST_BUNDLE]);
      }
    } else {
      $dest = $nodeStorage->create(['type' => self::DEST_BUNDLE]);
    }

    assert($dest instanceof NodeInterface);

    $isNew = $dest->isNew();
    $body = $source->get('body')->getValue();

    // --- Core node properties ---
    $dest->set('title', $source->label());
    $dest->set('status', (int) $source->isPublished());
    $dest->set('uid', $source->getOwnerId());
    $dest->set('langcode', $source->language()->getId());
    $dest->set('created', $source->getCreatedTime());
    $dest->set('changed', $source->getChangedTime());

    // --- Body and title ---
    if (!empty($body)) {
      $dest->set('body', $body);
    }
    $dest->set('field_title', [['value' => $source->label(), 'format' => 'full_html']]);

    // --- Direct field copy (same machine name on both bundles) ---
    foreach (self::DIRECT_FIELDS as $field) {
      if (!$source->hasField($field) || !$dest->hasField($field)) {
        continue;
      }
      if ($source->get($field)->isEmpty()) {
        continue;
      }
      $dest->set($field, $source->get($field)->getValue());
    }

    // --- Renamed field mapping: field_podcast_* (source) → field_vid_article_* (dest) ---
    foreach (self::RENAMED_FIELDS as $sourceField => $destField) {
      if (!$source->hasField($sourceField) || !$dest->hasField($destField)) {
        continue;
      }
      if ($source->get($sourceField)->isEmpty()) {
        continue;
      }
      $dest->set($destField, $source->get($sourceField)->getValue());
    }

    // --- Ensure Podcast term in field_eidos_arthrou ---
    if ($dest->hasField('field_eidos_arthrou')) {
      $this->addPodcastTermIfMissing($dest, $podcastTermId);
    }

    // --- Migration audit field ---
    if ($dest->hasField('field_migrated_id')) {
      $dest->set('field_migrated_id', $sourceNid);
    }

    // --- Defaults for fields with no Podcast equivalent ---
    if ($isNew) {
      if ($dest->hasField('field_show_summary')) {
        $dest->set('field_show_summary', FALSE);
      }
    }

    // setSyncing preserves the original created/changed timestamps.
    $dest->setSyncing(TRUE);
    $dest->save();

    $this->database->merge(self::MAP_TABLE)
      ->key('source_nid', $sourceNid)
      ->fields(['dest_nid' => (int) $dest->id()])
      ->execute();

    $this->migratePathAlias($source, $dest, $aliasStorage);

    return $isNew ? 'created' : 'updated';
  }

  private function migratePathAlias(
    NodeInterface $source,
    NodeInterface $dest,
    EntityStorageInterface $aliasStorage,
  ): void {
    $sourcePath = '/node/' . $source->id();

    $sourceAliases = $aliasStorage->loadByProperties([
      'path' => $sourcePath,
      'langcode' => $source->language()->getId(),
    ]);

    if (empty($sourceAliases)) {
      return;
    }

    $sourceAliasEntity = reset($sourceAliases);
    assert($sourceAliasEntity instanceof PathAliasInterface);
    $alias = $sourceAliasEntity->getAlias();
    $destPath = '/node/' . $dest->id();

    // Delete ALL existing aliases for the destination node (including any
    // auto-generated ones from pathauto that got a -0 suffix on save).
    $existing = $aliasStorage->loadByProperties(['path' => $destPath]);
    foreach ($existing as $existingAlias) {
      $existingAlias->delete();
    }

    $aliasStorage->create([
      'path' => $destPath,
      'alias' => $alias,
      'langcode' => $source->language()->getId(),
    ])->save();
  }

  /**
   * Validates that all required fields exist on both bundles before migrating.
   *
   * Returns TRUE if everything is in order, FALSE (with logged errors) if not.
   */
  private function preflight(): bool {
    $fieldManager = $this->entityFieldManager;
    $sourceFields = array_keys($fieldManager->getFieldDefinitions('node', self::SOURCE_BUNDLE));
    $destFields = array_keys($fieldManager->getFieldDefinitions('node', self::DEST_BUNDLE));

    // Fields that must exist on article_liberal for data to not be silently lost.
    $requiredOnDest = [
      'field_migrated_id',
      'field_vid_article_source_code',
      'field_vid_article_series',
      'field_vid_article_episode',
      'field_vid_article_duration',
    ];

    $pass = TRUE;

    foreach ($requiredOnDest as $field) {
      if (!in_array($field, $destFields, strict: true)) {
        $this->logger()?->error(
          'Preflight failed: {field} is missing on {bundle}. Attach the field storage before running the migration.',
          ['field' => $field, 'bundle' => self::DEST_BUNDLE]
        );
        $pass = FALSE;
      }
    }

    // Warn for DIRECT_FIELDS missing on the destination — will be skipped.
    foreach (self::DIRECT_FIELDS as $field) {
      if (!in_array($field, $sourceFields, strict: true)) {
        continue;
      }
      if (!in_array($field, $destFields, strict: true)) {
        $this->logger()?->warning(
          'Preflight warning: {field} exists on podcast but not on {bundle} — this field will be skipped.',
          ['field' => $field, 'bundle' => self::DEST_BUNDLE]
        );
      }
    }

    // Warn for RENAMED_FIELDS missing on either side.
    foreach (self::RENAMED_FIELDS as $sourceField => $destField) {
      if (!in_array($sourceField, $sourceFields, strict: true)) {
        $this->logger()?->warning(
          'Preflight warning: source field {field} not found on podcast — will be skipped.',
          ['field' => $sourceField]
        );
      }
      if (!in_array($destField, $destFields, strict: true)) {
        $this->logger()?->error(
          'Preflight failed: destination field {field} is missing on {bundle}.',
          ['field' => $destField, 'bundle' => self::DEST_BUNDLE]
        );
        $pass = FALSE;
      }
    }

    if ($pass) {
      $this->logger()?->notice('Preflight passed.');
    }

    return $pass;
  }

  private function ensureMapTable(): void {
    if ($this->database->schema()->tableExists(self::MAP_TABLE)) {
      return;
    }

    $this->database->schema()->createTable(self::MAP_TABLE, [
      'description' => 'Tracks podcast → article_liberal node ID mappings for rollback.',
      'fields' => [
        'source_nid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'Source podcast node ID.',
        ],
        'dest_nid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'Destination article_liberal node ID.',
        ],
      ],
      'primary key' => ['source_nid'],
    ]);
  }

  private function addPodcastTermIfMissing(NodeInterface $node, int $termId): void {
    $current = array_column($node->get('field_eidos_arthrou')->getValue(), 'target_id');
    if (in_array($termId, $current, strict: true)) {
      return;
    }
    $node->get('field_eidos_arthrou')->appendItem(['target_id' => $termId]);
  }

  private function ensurePodcastTerm(): int {
    // Μία πηγή για το όνομα του όρου. Εδώ υπήρχε hardcoded 'Video Article' που
    // δημιουργούσε δεύτερο όρο δίπλα σε αυτόν του unicorn_video_article, οπότε τα
    // migrated podcasts κατέληγαν σε άλλη κατηγορία από όσα φτιάχνει ο συντάκτης.
    // Η ανάλυση ορίζει τα είδη ως Απλό, Διαφημιστικό, Βίντεο.
    $this->moduleHandler->loadInclude('unicorn_video_article', 'install');

    return _unicorn_video_article_article_type_term_id();
  }

}
