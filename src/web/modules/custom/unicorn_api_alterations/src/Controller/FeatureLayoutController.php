<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Feature dashboard save — bulk-assigns articles to a Feature (US 12.6).
 *
 * A Feature = an oblations term. Its inner page has two zones:
 *   Zone A (Ob Zone A) → Big-2 Main   ·   Zone B (Ob Zone B) → List below.
 * Membership + order live on the article nodes (field_oblation_category +
 * field_article_region + field_oblation_zone_a/b_weight) — exactly like the old
 * Oblations dashboard. This endpoint writes them in one call (full replace):
 * articles removed from the Feature are unassigned, the rest get their zone and
 * position from the array order.
 */
final class FeatureLayoutController implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
  }

  public function save(Request $request): JsonResponse {
    $payload = json_decode($request->getContent() ?: '[]', TRUE);
    $payload = is_array($payload) ? $payload : [];
    $feature_uuid = (string) ($payload['feature'] ?? '');
    $zone_a = array_map(strval(...), array_values((array) ($payload['zone_a'] ?? [])));
    $zone_b = array_map(strval(...), array_values((array) ($payload['zone_b'] ?? [])));

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $features = $feature_uuid ? $term_storage->loadByProperties(['uuid' => $feature_uuid]) : [];
    $feature = reset($features);
    if (!$feature instanceof TermInterface || $feature->bundle() !== 'oblations') {
      return new JsonResponse(['error' => 'Unknown feature — send the uuid of an oblations term.'], 422);
    }
    $feature_tid = (int) $feature->id();

    // Region terms (Ob Zone A / Ob Zone B) by name — no hardcoded tids.
    $regions = [];
    foreach ($term_storage->loadByProperties(['vid' => 'oblation_regions']) as $region) {
      $regions[(string) $region->label()] = (int) $region->id();
    }
    if (!isset($regions['Ob Zone A'], $regions['Ob Zone B'])) {
      return new JsonResponse(['error' => 'Missing Ob Zone A / Ob Zone B region terms.'], 500);
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $wanted = array_fill_keys([...$zone_a, ...$zone_b], TRUE);

    // Full replace: unassign articles removed from this Feature.
    $current = $node_storage->getQuery()
      ->condition('field_oblation_category', $feature_tid)
      ->accessCheck(FALSE)
      ->execute();
    foreach ($node_storage->loadMultiple($current) as $node) {
      if (!$node instanceof FieldableEntityInterface) {
        continue;
      }
      if (!isset($wanted[$node->uuid()])) {
        $node->set('field_oblation_category', NULL);
        $node->set('field_article_region', NULL);
        $node->save();
      }
    }

    $count = $this->assignZone($node_storage, $zone_a, $feature_tid, $regions['Ob Zone A'], 'field_oblation_zone_a_weight')
      + $this->assignZone($node_storage, $zone_b, $feature_tid, $regions['Ob Zone B'], 'field_oblation_zone_b_weight');

    return new JsonResponse(['saved' => TRUE, 'feature' => $feature_uuid, 'count' => $count]);
  }

  /**
   * Assigns the ordered articles of one zone to the Feature.
   *
   * @param string[] $uuids
   *   Article UUIDs in display order.
   */
  private function assignZone(EntityStorageInterface $node_storage, array $uuids, int $feature_tid, int $region_tid, string $weight_field): int {
    $saved = 0;
    foreach ($uuids as $position => $uuid) {
      $matches = $node_storage->loadByProperties(['uuid' => $uuid]);
      $node = reset($matches);
      if (!$node instanceof FieldableEntityInterface) {
        continue;
      }
      $node->set('field_oblation_category', $feature_tid);
      $node->set('field_article_region', $region_tid);
      $node->set($weight_field, $position);
      $node->save();
      $saved++;
    }

    return $saved;
  }

}
