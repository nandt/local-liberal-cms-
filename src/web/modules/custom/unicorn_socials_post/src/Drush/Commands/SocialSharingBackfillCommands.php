<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Symfony\Component\Console\Command\Command;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class SocialSharingBackfillCommands extends DrushCommands {

  private const int CHUNK = 100;

  use StringTranslationTrait;
  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'unicorn_socials_post:backfill_social_sharing')]
  public function backfillSocialSharing(): int {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'article_liberal')
      ->accessCheck(FALSE)
      ->execute();

    if (!$nids) {
      return Command::SUCCESS;
    }

    $chunks = array_chunk($nids, self::CHUNK);
    $total = count($nids);

    $batchBuilder = new BatchBuilder();

    foreach ($chunks as $chunk) {
      $batchBuilder->addOperation([self::class, 'processChunk'], [
        $total,
        $chunk,
      ]);
    }

    $batchBuilder
      ->setTitle($this->t('Backfilling social sharing counters'))
      ->setFinishCallback([self::class, 'backfillFinished'])
      ->setErrorMessage($this->t('Batch has encountered an error'));

    batch_set($batchBuilder->toArray());
    drush_backend_batch_process();

    return Command::SUCCESS;
  }

  /**
   * Batch process callback.
   *
   * Each operation is executed by Drush in its own subprocess (see
   * drush_backend_batch_process()), so entity static caches, the database
   * query log, and any other per-request state are wiped clean between
   * chunks automatically - no manual resetCache()/query-log workarounds
   * needed to keep memory bounded.
   *
   * @param int $total
   *   The total number of nodes to be processed.
   * @param int[] $chunk
   *   Array of node ids to process in this batch operation.
   * @param array<string, mixed> $context
   *   Context for operations.
   */
  public static function processChunk(int $total, array $chunk, array &$context): void {
    $database = \Drupal::database();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

    if (!isset($context['results']['processed'])) {
      $context['results']['processed'] = 0;
      $context['results']['written'] = 0;
      $context['results']['total'] = $total;
    }

    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $context['results']['processed']++;
      $row = [];

      foreach (self::getMappedFields() as $fieldName => $columnName) {
        if ($node->get($fieldName)->isEmpty()) {
          continue;
        }

        $row[$columnName] = (int) $node->get($fieldName)->value;
      }

      if ($row !== []) {
        $database->merge('unicorn_social_sharing')
          ->key('entity_id', (int) $node->id())
          ->fields($row)
          ->execute();
        $context['results']['written']++;
      }
    }

    $context['message'] = t('Processed @processed / @total nodes.', [
      '@processed' => $context['results']['processed'],
      '@total' => $total,
    ]);
  }

  /**
   * @param array<string, mixed> $results
   * @param array<int, mixed> $operations
   */
  public static function backfillFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      \Drupal::messenger()->addStatus(t('Backfill finished. Processed @processed / @total nodes, wrote @written rows.', [
        '@processed' => $results['processed'] ?? 0,
        '@total' => $results['total'] ?? 0,
        '@written' => $results['written'] ?? 0,
      ]));
    }
    else {
      \Drupal::messenger()->addError(t('There was an error during the backfill operation.'));
    }
  }

  /**
   * Deletes the legacy social sharing fields from article_liberal.
   * We depend on cron to delete the data.
   */
  #[CLI\Command(name: 'unicorn_socials_post:delete_legacy_social_fields')]
  public function deleteLegacySocialFields(): int {
    if (!$this->io()->confirm('This will permanently delete the legacy social sharing fields from article_liberal (data will be purged by cron). Make sure the backfill has been verified first. Continue?', FALSE)) {
      $this->logger()?->notice('Aborted, nothing was deleted.');
      return Command::SUCCESS;
    }

    foreach (array_keys(self::getMappedFields()) as $fieldName) {
      $fieldConfig = FieldConfig::loadByName('node', 'article_liberal', $fieldName);
      if ($fieldConfig) {
        $fieldConfig->delete();
        $this->logger()?->notice('Deleted field instance {field}.', ['field' => $fieldName]);
      }

      $fieldStorage = FieldStorageConfig::loadByName('node', $fieldName);
      if ($fieldStorage && !$fieldStorage->isDeleted()) {
        $fieldStorage->delete();
        $this->logger()?->notice('Deleted field storage {field}.', ['field' => $fieldName]);
      }
    }

    $this->logger()?->success('Legacy social sharing fields marked as deleted. Data and tables will be purged gradually by cron.');

    return Command::SUCCESS;
  }

  /**
   * @return string[]
   */
  public static function getMappedFields(): array {
    return [
      'field_post_fb_liberal_counter' => 'facebook_times_shared',
      'field_post_fb_liberal_latest_tim' => 'facebook_last_sharing_date',
      'field_post_twitter_liberal_count' => 'x_times_shared',
      'field_post_twitter_liberal_lates' => 'x_last_sharing_date',
      'field_notification_liberal_count' => 'notification_times_sent',
      'field_notification_liberal_time' => 'last_notification_date',
    ];
  }

}
