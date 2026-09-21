<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_api_alterations\Entity\Section;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Home layout — η διάταξη των Section entities στην αρχική ανά variant.
 *
 * State blob (full-replace): κάθε placement δείχνει σε ένα Section entity (μέσω
 * uuid) + τα article_ids που το γεμίζουν. Το blob κρατάει ΜΟΝΟ την αναφορά + τα
 * άρθρα· template/όνομα έρχονται από το Section entity κατά το read (single
 * source of truth — χωρίς staleness αν αλλάξει το section).
 *
 * Το composite section (Home) ορίζεται στον κώδικα και δεν αποθηκεύεται ως
 * placement: το state κρατάει μόνο τα άρθρα των sub-sections του.
 */
class HomePageLayoutController extends ControllerBase {

  const STATE_KEY = 'liberal.home_page_editor';

  /**
   * Τα σταθερά composite sections ανά variant.
   */
  const COMPOSITE_SECTIONS = [
    'liberal' => [
      'key' => 'home',
      'display_name' => 'Home',
      'template' => 'home',
      'subsections' => [
        ['key' => 'main', 'display_name' => 'Main', 'article_slot' => 3],
        ['key' => 'secondary', 'display_name' => 'Secondary', 'article_slot' => 3],
        ['key' => 'black_box', 'display_name' => 'Black Box', 'article_slot' => 1],
        ['key' => 'blue_articles', 'display_name' => 'Blue Articles', 'article_slot' => 3],
        ['key' => 'top_stories', 'display_name' => 'Top Stories', 'article_slot' => 13],
      ],
    ],
  ];

  /**
   * Τα σταθερά sub-sections όσων templates είναι composite.
   */
  const COMPOSITE_TEMPLATES = [
    'liberal_markets' => [
      ['key' => 'home_lm', 'display_name' => 'Home LM', 'article_slot' => 5],
      ['key' => 'top_stories', 'display_name' => 'Top Stories', 'article_slot' => 6],
    ],
  ];

  /**
   * Επιστρέφει τη διάταξη ενός variant, με το composite section πάντα πρώτο.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Το αίτημα. Διαβάζεται το query parameter "variant".
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Τα sections σε σειρά εμφάνισης, εμπλουτισμένα από τα Section entities.
   */
  public function get(Request $request): JsonResponse {
    $variant = (string) $request->query->get('variant', 'liberal');
    $stored = $this->storedLayout($variant);

    $sections = [];

    if ($composite = self::COMPOSITE_SECTIONS[$variant] ?? NULL) {
      $sections[] = $this->buildComposite($composite, $stored['composite']);
    }

    foreach ($stored['sections'] as $placement) {
      $sections[] = $this->buildPlacement($placement);
    }

    return new JsonResponse(['variant' => $variant, 'sections' => $sections]);
  }

  /**
   * Αποθηκεύει τη διάταξη ενός variant.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Το αίτημα, με JSON body {variant, sections}.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Επιβεβαίωση, ή 422 αν κάποιο placement δείχνει σε ανύπαρκτο Section.
   */
  public function save(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE) ?: [];
    $variant = (string) ($payload['variant'] ?? 'liberal');
    $incoming = $payload['sections'] ?? [];

    $composite = self::COMPOSITE_SECTIONS[$variant] ?? NULL;
    $stored = $this->storedLayout($variant);

    $placements = [];
    $invalid = [];
    $seen = [];
    $composite_articles = $stored['composite'];

    $previous = [];
    foreach ($stored['sections'] as $placement) {
      if (isset($placement['uuid'])) {
        $previous[$placement['uuid']] = $placement;
      }
    }

    foreach ($incoming as $entry) {
      // Το frontend επιστρέφει το id όπως το έλαβε, οπότε δεχόμαστε και τα δύο
      // ονόματα: key/uuid για όποιον τα ξεχωρίζει, id για τον απλό κανόνα.
      if ($composite && ($entry['key'] ?? $entry['id'] ?? NULL) === $composite['key']) {
        $composite_articles = $this->articlesPerSubsection($entry, $composite, $composite_articles);
        continue;
      }

      $uuid = $entry['uuid'] ?? $entry['id'] ?? NULL;
      $section = $uuid ? $this->loadSection($uuid) : NULL;
      if (!$section) {
        $invalid[] = $uuid ?: '(empty)';
        continue;
      }

      $seen[$uuid] = TRUE;
      $subsections = self::COMPOSITE_TEMPLATES[(string) $section->get('template')->value] ?? NULL;

      if ($subsections === NULL) {
        $placements[] = [
          'uuid' => $uuid,
          'article_ids' => array_values($entry['article_ids'] ?? []),
        ];

        continue;
      }

      $fallback = $previous[$uuid]['subsections']
        ?? $this->splitFlat($previous[$uuid]['article_ids'] ?? [], $subsections);

      if (!isset($entry['subsections']) && isset($entry['article_ids'])) {
        $entry['subsections'] = [];
        foreach ($this->splitFlat($entry['article_ids'], $subsections) as $key => $ids) {
          $entry['subsections'][] = ['key' => $key, 'article_ids' => $ids];
        }
      }

      $placements[] = [
        'uuid' => $uuid,
        'subsections' => $this->articlesPerSubsection($entry, ['subsections' => $subsections], $fallback),
      ];
    }

    // Τα composite sections δεν διαγράφονται (η ανάλυση κρύβει το delete icon),
    // οπότε όποιο έλειπε από το payload επιστρέφει με ό,τι είχε.
    foreach ($previous as $uuid => $placement) {
      if (isset($seen[$uuid]) || !isset($placement['subsections'])) {
        continue;
      }

      $placements[] = $placement;
    }

    if ($invalid) {
      return new JsonResponse([
        'error' => 'Unknown section uuid(s) — κάθε section πρέπει να δείχνει σε υπαρκτό Section entity.',
        'invalid' => $invalid,
      ], 422);
    }

    $this->state()->set(self::STATE_KEY . '.' . $variant, [
      'composite' => $composite_articles,
      'sections' => $placements,
    ]);

    if (function_exists('unicorn_api_alterations_home_layout_saved')) {
      unicorn_api_alterations_home_layout_saved($variant, $placements);
    }

    return new JsonResponse([
      'saved' => TRUE,
      'variant' => $variant,
      'count' => count($placements) + ($composite ? 1 : 0),
    ]);
  }

  /**
   * Το state σε σταθερό σχήμα, δεχόμενο και το παλιό σκέτο array placements.
   *
   * @param string $variant
   *   Το variant του home layout.
   *
   * @return array{composite: array<string, list<mixed>>, sections: list<array<string, mixed>>}
   *   Το composite περιεχόμενο και τα regular placements.
   */
  private function storedLayout(string $variant): array {
    $state = $this->state()->get(self::STATE_KEY . '.' . $variant, []);

    if (!isset($state['sections']) && !isset($state['composite'])) {
      return ['composite' => [], 'sections' => is_array($state) ? array_values($state) : []];
    }

    return [
      'composite' => $state['composite'] ?? [],
      'sections' => array_values($state['sections'] ?? []),
    ];
  }

  /**
   * Χτίζει το composite section από τη σταθερά και το αποθηκευμένο περιεχόμενο.
   *
   * @param array<string, mixed> $composite
   *   Ο ορισμός του composite από τη COMPOSITE_SECTIONS.
   * @param array<string, list<mixed>> $articles
   *   Τα article ids ανά sub-section key.
   *
   * @return array<string, mixed>
   *   Το section όπως επιστρέφεται στο API.
   */
  private function buildComposite(array $composite, array $articles): array {
    $subsections = [];
    $flat = [];
    $total = 0;

    foreach ($composite['subsections'] as $subsection) {
      $total += $subsection['article_slot'];
      $ids = array_values($articles[$subsection['key']] ?? []);
      $flat = array_merge($flat, $ids);
      $subsections[] = $subsection + ['article_ids' => $ids];
    }

    return [
      // Το id είναι κοινό σε κάθε section: uuid όπου υπάρχει entity, key εδώ.
      // Ο front-end το επιστρέφει αυτούσιο και δεν χρειάζεται ειδική περίπτωση.
      'id' => $composite['key'],
      'key' => $composite['key'],
      'type' => 'composite',
      'internal_name' => $composite['key'],
      'display_name' => $composite['display_name'],
      'template' => $composite['template'],
      'article_slot' => $total,
      'deletable' => FALSE,
      'reorderable' => FALSE,
      // Επίπεδη λίστα σε σειρά εμφάνισης, παράλληλα με τα subsections: ο public
      // renderer διαβάζει article_ids και δεν πρέπει να σπάσει.
      'article_ids' => $flat,
      'subsections' => $subsections,
    ];
  }

  /**
   * Χτίζει ένα regular placement, εμπλουτισμένο από το Section entity.
   *
   * @param array<string, mixed> $placement
   *   Το αποθηκευμένο placement (uuid + article_ids).
   *
   * @return array<string, mixed>
   *   Το section όπως επιστρέφεται στο API, με missing=TRUE αν διαγράφηκε.
   */
  private function buildPlacement(array $placement): array {
    $uuid = $placement['uuid'] ?? NULL;
    $section = $uuid ? $this->loadSection($uuid) : NULL;

    if (!$section) {
      return [
        'id' => $uuid,
        'uuid' => $uuid,
        'type' => 'section',
        'deletable' => TRUE,
        'reorderable' => TRUE,
        'article_ids' => array_values($placement['article_ids'] ?? []),
        'missing' => TRUE,
      ];
    }

    $template = (string) $section->get('template')->value;
    $subsections = self::COMPOSITE_TEMPLATES[$template] ?? NULL;

    $entry = [
      'id' => $uuid,
      'uuid' => $uuid,
      // Το composite section μετακινείται ανάμεσα στα υπόλοιπα αλλά δεν
      // διαγράφεται — η ανάλυση δεν εμφανίζει delete icon γι' αυτό.
      'type' => $subsections === NULL ? 'section' : 'composite',
      'deletable' => $subsections === NULL,
      'reorderable' => TRUE,
      'internal_name' => $section->get('internal_name')->value,
      'display_name' => $section->get('display_name')->value,
      'template' => $template,
      'article_slot' => (int) $section->get('article_slot')->value,
    ];

    if ($subsections === NULL) {
      $entry['article_ids'] = array_values($placement['article_ids'] ?? []);

      return $entry;
    }

    // Παλιό state ή frontend που δεν έχει υιοθετήσει ακόμα τα subsections:
    // η επίπεδη λίστα μοιράζεται στις ομάδες με τη σειρά, ακριβώς όπως την
    // ερμηνεύει σήμερα ο public renderer.
    $stored = $placement['subsections'] ?? $this->splitFlat($placement['article_ids'] ?? [], $subsections);
    $entry['subsections'] = [];
    $flat = [];

    foreach ($subsections as $subsection) {
      $ids = array_values($stored[$subsection['key']] ?? []);
      $flat = array_merge($flat, $ids);
      $entry['subsections'][] = $subsection + ['article_ids' => $ids];
    }

    $entry['article_ids'] = $flat;

    return $entry;
  }

  /**
   * Ενημερώνει τα article ids των sub-sections που στάλθηκαν.
   *
   * Sub-section που λείπει από το payload, ή που ήρθε χωρίς article_ids,
   * κρατάει ό,τι είχε. Ρητό άδειο array καθαρίζει το sub-section.
   *
   * @param array<string, mixed> $entry
   *   Το composite entry όπως ήρθε από το frontend.
   * @param array<string, mixed> $composite
   *   Ο ορισμός του composite από τη COMPOSITE_SECTIONS.
   * @param array<string, list<mixed>> $stored
   *   Τα ήδη αποθηκευμένα article ids ανά sub-section key.
   *
   * @return array<string, list<mixed>>
   *   Τα article ids ανά γνωστό sub-section key.
   */
  private function articlesPerSubsection(array $entry, array $composite, array $stored): array {
    $known = array_column($composite['subsections'], 'key');
    $articles = $stored;

    foreach ($entry['subsections'] ?? [] as $subsection) {
      $key = $subsection['key'] ?? NULL;

      if (!in_array($key, $known, TRUE) || !array_key_exists('article_ids', $subsection)) {
        continue;
      }

      $articles[$key] = array_values($subsection['article_ids']);
    }

    return $articles;
  }

  /**
   * Μοιράζει επίπεδη λίστα άρθρων στις ομάδες, με τη σειρά και τα slots τους.
   *
   * @param list<mixed> $flat
   *   Η επίπεδη λίστα article ids.
   * @param list<array<string, mixed>> $subsections
   *   Οι ορισμοί των sub-sections.
   *
   * @return array<string, list<mixed>>
   *   Τα article ids ανά sub-section key.
   */
  private function splitFlat(array $flat, array $subsections): array {
    $out = [];
    $offset = 0;

    foreach ($subsections as $subsection) {
      $out[$subsection['key']] = array_slice($flat, $offset, $subsection['article_slot']);
      $offset += $subsection['article_slot'];
    }

    return $out;
  }

  /**
   * Φορτώνει Section entity από uuid.
   *
   * @param string $uuid
   *   Το uuid του Section.
   *
   * @return \Drupal\unicorn_api_alterations\Entity\Section|null
   *   Το entity, ή NULL αν δεν υπάρχει.
   */
  private function loadSection(string $uuid): ?Section {
    $matches = $this->entityTypeManager()
      ->getStorage('section')
      ->loadByProperties(['uuid' => $uuid]);

    $section = reset($matches);

    return $section instanceof Section ? $section : NULL;
  }

}
