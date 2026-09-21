<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\State\StateInterface;

/**
 * Answers whether an entity may still be deleted from the dashboard.
 *
 * Criteria per analysis: US 3.4 Section, US 5.4 Author, US 7.4 Category,
 * US 12.5 Feature, US 13.4 Source, US 13.8 Series. Each set is loaded once
 * per request so listing pages stay at a fixed number of queries.
 */
final class DeletionEligibility {

  private const string HOME_LAYOUT_STATE_PREFIX = 'liberal.home_page_editor.';

  private const array HOME_LAYOUT_VARIANTS = ['liberal', 'markets'];

  /**
   * Term bundles locked by an article reference: bundle => article field table.
   */
  private const array TERM_USAGE_TABLES = [
    'series' => 'node__field_vid_article_series',
    'category' => 'node__field_liberal_category',
    'piges_arthron' => 'node__field_pigi_arthroy',
    'arthrografos' => 'node__field_arthrografos',
  ];

  /** @var array<string, array<int, true>> */
  private array $referencedTerms = [];

  /** @var array<string, true>|null */
  private ?array $sectionsInUse = NULL;

  /** @var array<int, true>|null */
  private ?array $featuresInUse = NULL;

  /** @var array<int, true>|null */
  private ?array $parentTerms = NULL;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
  ) {}

  public function isDeletable(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() === 'section') {
      return !isset($this->sectionsInUse()[$entity->uuid()]);
    }

    $tid = (int) $entity->id();

    return match ($entity->bundle()) {
      'oblations' => !isset($this->featuresInUse()[$tid]),
      'category' => !isset($this->referencedTerms('category')[$tid]) && !isset($this->parentTerms()[$tid]),
      'series', 'piges_arthron', 'arthrografos' => !isset($this->referencedTerms($entity->bundle())[$tid]),
      default => TRUE,
    };
  }

  /**
   * Sections placed on any homepage variant (US 3.4).
   *
   * @return array<string, true>
   */
  private function sectionsInUse(): array {
    if ($this->sectionsInUse !== NULL) {
      return $this->sectionsInUse;
    }

    $used = [];
    foreach (self::HOME_LAYOUT_VARIANTS as $variant) {
      foreach ((array) $this->state->get(self::HOME_LAYOUT_STATE_PREFIX . $variant, []) as $placement) {
        if (!empty($placement['uuid'])) {
          $used[(string) $placement['uuid']] = TRUE;
        }
      }
    }

    return $this->sectionsInUse = $used;
  }

  /**
   * Features referenced by at least one Section (US 12.5).
   *
   * @return array<int, true>
   */
  private function featuresInUse(): array {
    if ($this->featuresInUse !== NULL) {
      return $this->featuresInUse;
    }

    $used = [];
    foreach ($this->entityTypeManager->getStorage('section')->loadMultiple() as $section) {
      if (!$section instanceof FieldableEntityInterface || !$section->hasField('feature')) {
        continue;
      }
      $tid = (int) ($section->get('feature')->target_id ?? 0);
      if ($tid) {
        $used[$tid] = TRUE;
      }
    }

    return $this->featuresInUse = $used;
  }

  /**
   * Terms referenced by at least one article (US 7.4, 13.4, 13.8).
   *
   * Drafts count: the analysis says "associated articles", without a status
   * qualifier, so an unpublished article still locks the term.
   *
   * @return array<int, true>
   */
  private function referencedTerms(string $bundle): array {
    if (isset($this->referencedTerms[$bundle])) {
      return $this->referencedTerms[$bundle];
    }

    $table = self::TERM_USAGE_TABLES[$bundle] ?? NULL;
    if ($table === NULL || !$this->database->schema()->tableExists($table)) {
      return $this->referencedTerms[$bundle] = [];
    }

    $column = str_replace('node__', '', $table) . '_target_id';
    $tids = $this->database->select($table, 't')
      ->fields('t', [$column])
      ->distinct()
      ->execute()
      ?->fetchCol() ?? [];

    return $this->referencedTerms[$bundle] = array_fill_keys(array_map(intval(...), $tids), TRUE);
  }

  /**
   * Terms used as a parent by another term (US 7.4, second condition).
   *
   * @return array<int, true>
   */
  private function parentTerms(): array {
    if ($this->parentTerms !== NULL) {
      return $this->parentTerms;
    }

    if (!$this->database->schema()->tableExists('taxonomy_term__parent')) {
      return $this->parentTerms = [];
    }

    $tids = $this->database->select('taxonomy_term__parent', 'p')
      ->fields('p', ['parent_target_id'])
      ->condition('p.parent_target_id', 0, '>')
      ->distinct()
      ->execute()
      ?->fetchCol() ?? [];

    return $this->parentTerms = array_fill_keys(array_map(intval(...), $tids), TRUE);
  }

}
