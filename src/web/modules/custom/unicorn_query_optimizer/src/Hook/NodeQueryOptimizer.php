<?php

declare(strict_types=1);

namespace Drupal\unicorn_query_optimizer\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Ξαναγράφει τα node listing queries ώστε να τρέχουν σε χιλιοστά (LIBER-4480).
 *
 * Δες το README για το τι διορθώνει και γιατί. Ο κανόνας εδώ: σε κάθε σχήμα που
 * δεν αναγνωρίζουμε με βεβαιότητα, βγαίνουμε αφήνοντας το query ανέγγιχτο.
 */
final class NodeQueryOptimizer {

  /**
   * Κάτω από αυτό το ποσοστό του πίνακα, το φίλτρο όντως ξεχωρίζει κάτι και ο
   * optimizer έχει δίκιο — το fence θα έκανε ζημιά.
   */
  private const float FENCE_RATIO = 0.05;

  private int $placeholder = 0;

  public function __construct(
    private readonly Connection $database,
    private readonly StateInterface $state,
  ) {}

  #[Hook('query_entity_query_node_alter')]
  public function alterNodeQuery(AlterableInterface $query): void {
    if (!$query instanceof SelectInterface) {
      return;
    }
    if (!$this->state->get('unicorn_query_optimizer.enabled', TRUE)) {
      return;
    }
    if ($this->hasTranslations()) {
      return;
    }

    $this->rewrite($query);
  }

  /**
   * Το countQuery() τυλίγει το βαρύ select σε subquery χωρίς να το ξανα-taggάρει,
   * οπότε το πιάνουμε εδώ — αλλιώς το COUNT μένει στα 4 δευτερόλεπτα.
   */
  private function rewrite(SelectInterface $query): void {
    foreach ($query->getTables() as $table) {
      if (($table['table'] ?? NULL) instanceof SelectInterface) {
        $this->rewrite($table['table']);
      }
    }

    if ($query->getMetaData('all_revisions')) {
      return;
    }

    $tables = &$query->getTables();
    if (($tables['base_table']['table'] ?? NULL) !== 'node') {
      return;
    }

    // Το ένα node_field_data κρατάει τα conditions, τα υπόλοιπα είναι αντίγραφα
    // που το core προσθέτει ένα ανά sort column.
    $data = NULL;
    $copies = [];
    foreach ($tables as $alias => $table) {
      if ($alias === 'base_table') {
        continue;
      }
      if (($table['table'] ?? NULL) !== 'node_field_data' || !empty($table['arguments'])) {
        return;
      }
      // Το Drupal 11 γράφει τα join conditions με αγκύλες.
      $condition = trim(str_replace(['[', ']'], '', (string) ($table['condition'] ?? '')));
      if ($condition !== "$alias.nid = base_table.nid") {
        return;
      }
      if (($table['join type'] ?? '') === 'INNER' && $data === NULL) {
        $data = (string) $alias;
        continue;
      }
      $copies[$alias] = TRUE;
    }
    if ($data === NULL) {
      return;
    }

    $group_by = &$query->getGroupBy();
    foreach ($group_by as $column) {
      if ($column !== 'base_table.vid' && $column !== 'base_table.nid') {
        return;
      }
    }

    $fields = &$query->getFields();
    foreach ($fields as $field) {
      if (!in_array($field['table'] ?? '', ['base_table', $data], TRUE)) {
        return;
      }
    }

    // Χωρίς το ξαναγράψιμο των max()/min() σε σκέτη στήλη, σβήνοντας το GROUP BY
    // το aggregate απλώνεται σε όλο τον πίνακα και γυρίζει μία γραμμή.
    $expressions = &$query->getExpressions();
    $rewritten = [];
    foreach ($expressions as $alias => $expression) {
      if (!empty($expression['arguments'])) {
        return;
      }
      $sql = trim((string) ($expression['expression'] ?? ''));
      // Το COUNT wrapper βάζει SELECT 1 — δεν έχει τι να ξαναγραφτεί.
      if (preg_match('/^\d+$/', $sql) === 1) {
        continue;
      }
      if (preg_match('/^(?:max|min)\(([a-z0-9_]+)\.([a-z0-9_]+)\)$/i', $sql, $matches) !== 1) {
        return;
      }
      // Το 11.3 βάζει το aggregate σε αντίγραφο του node_field_data, το 11.4
      // στον ίδιο τον πίνακα. Και τα δύο είναι 1:1 με τον κόμβο.
      if ($matches[1] !== $data && !isset($copies[$matches[1]])) {
        return;
      }
      $rewritten[$alias] = $data . '.' . $matches[2];
    }

    if (!$this->conditionsAreKnown($query->conditions(), $data, $copies)) {
      return;
    }

    $group_by = [];

    // Τα conditions που δείχνουν σε αντίγραφο (π.χ. filter[status] του JSON:API)
    // πρέπει να ξαναδεθούν στον πίνακα που μένει, αλλιώς το join που σβήνουμε
    // παρακάτω αφήνει πίσω του "Unknown column".
    $this->rebindConditions($query->conditions(), $data, $copies);

    $order_by = &$query->getOrderBy();
    $sorts = [];
    foreach ($order_by as $alias => $direction) {
      $sorts[$rewritten[$alias] ?? $alias] = $direction;
    }
    foreach (array_keys($rewritten) as $alias) {
      unset($expressions[$alias]);
    }
    if ($sorts !== []) {
      // Ίδια φορά με το τελευταίο sort, αλλιώς η MariaDB δεν μπορεί να
      // διασχίσει το index και ξαναπέφτει σε filesort.
      $sorts[$data . '.nid'] = end($sorts);
    }
    $order_by = $sorts;

    foreach (array_keys($copies) as $alias) {
      unset($tables[$alias]);
    }
    unset($tables['base_table'], $tables[$data]['join type'], $tables[$data]['condition']);
    // Απαραίτητο: το Select::__toString() διαβάζει το arguments του base table.
    $tables[$data]['arguments'] = [];

    foreach ($fields as $key => $field) {
      if (($field['table'] ?? '') === 'base_table') {
        $fields[$key]['table'] = $data;
      }
    }

    // Μία γραμμή ανά κόμβο εκ κατασκευής — αυτό αντικαθιστά το GROUP BY.
    $query->condition($data . '.default_langcode', 1);

    if ($sorts !== []) {
      $this->fenceIndexes($query, $data);
    }
  }

  /**
   * Κάθε condition πρέπει να δείχνει σε πίνακα που ξέρουμε ότι είναι 1:1 με τον
   * κόμβο. Ένα join σε field table θα πολλαπλασίαζε γραμμές.
   *
   * @param array<mixed> $conditions
   *   Ο πίνακας conditions του Select.
   * @param string $data
   *   Το alias του node_field_data που κρατάει τα conditions.
   * @param array<string, bool> $copies
   *   Τα aliases των αντιγράφων που προστέθηκαν για τα sort columns.
   */
  private function conditionsAreKnown(array $conditions, string $data, array $copies): bool {
    foreach ($conditions as $key => $condition) {
      if (!is_int($key)) {
        continue;
      }
      if (($condition['field'] ?? NULL) instanceof Condition) {
        if (!$this->conditionsAreKnown($condition['field']->conditions(), $data, $copies)) {
          return FALSE;
        }
        continue;
      }
      if (!is_string($condition['field'] ?? NULL)) {
        return FALSE;
      }
      $table = strtok(str_replace(['[', ']'], '', $condition['field']), '.');
      if ($table !== 'base_table' && $table !== $data && !isset($copies[$table])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Δείχνει κάθε condition αντιγράφου στο node_field_data που κρατάμε.
   *
   * @param array<mixed> $conditions
   *   Ο πίνακας conditions του Select, by reference.
   * @param string $data
   *   Το alias του node_field_data που κρατάει τα conditions.
   * @param array<string, bool> $copies
   *   Τα aliases των αντιγράφων που θα σβηστούν.
   */
  private function rebindConditions(array &$conditions, string $data, array $copies): void {
    foreach ($conditions as $key => &$condition) {
      if (!is_int($key)) {
        continue;
      }
      if (($condition['field'] ?? NULL) instanceof Condition) {
        $this->rebindConditions($condition['field']->conditions(), $data, $copies);
        continue;
      }
      if (!is_string($condition['field'] ?? NULL)) {
        continue;
      }
      $parts = explode('.', str_replace(['[', ']'], '', $condition['field']), 2);
      if (count($parts) === 2 && isset($copies[$parts[0]])) {
        $condition['field'] = $data . '.' . $parts[1];
      }
    }
  }

  /**
   * Κρύβει τα type/status πίσω από no-op expression ώστε να μην είναι πια
   * indexable. Ίδιες τιμές, αλλά ο optimizer δεν μπορεί να διαλέξει
   * index_merge και μένει με το index του ORDER BY.
   *
   * Μπαίνει μόνο όταν το φίλτρο δεν ξεχωρίζει τίποτα — αλλιώς κάνει ζημιά.
   */
  private function fenceIndexes(SelectInterface $query, string $data): void {
    $conditions = &$query->conditions();
    $targets = [];
    foreach ($conditions as $index => $condition) {
      if (!is_int($index) || !is_string($condition['field'] ?? NULL)) {
        continue;
      }
      if (($condition['operator'] ?? '=') !== '=' || is_array($condition['value'] ?? NULL)) {
        continue;
      }
      $field = str_replace(['[', ']'], '', $condition['field']);
      if ($field === "$data.type" || $field === "$data.status") {
        $targets[$index] = [$field, $condition['value']];
      }
    }
    if ($targets === []) {
      return;
    }

    if ($this->scannedRatio($query) < self::FENCE_RATIO) {
      return;
    }

    foreach ($targets as $index => [$field, $value]) {
      unset($conditions[$index]);
      $placeholder = ':unicorn_qo_' . $this->placeholder++;
      $query->where("IF(1, $field, NULL) = $placeholder", [$placeholder => $value]);
    }
  }

  /**
   * Πόσο του πίνακα σκοπεύει να διαβάσει ο optimizer, κατά δική του ομολογία.
   */
  private function scannedRatio(SelectInterface $query): float {
    try {
      $explain = $this->database->query('EXPLAIN ' . (string) $query, $query->getArguments());
      if ($explain === NULL) {
        return 0.0;
      }

      $rows = 0;
      foreach ($explain->fetchAll() as $row) {
        $rows = max($rows, (int) $row->rows);
      }

      $count = $this->database->query(
        'SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        [':table' => $this->database->getPrefix() . 'node_field_data']
      );
      $total = $count === NULL ? 0 : (int) $count->fetchField();
    }
    catch (\Throwable) {
      return 0.0;
    }

    return $total > 0 ? $rows / $total : 0.0;
  }

  /**
   * Το isMultilingual() επιστρέφει TRUE επειδή υπάρχει configured γλώσσα, ακόμα
   * κι αν δεν έχει μεταφραστεί τίποτα. Μας ενδιαφέρει μόνο το δεύτερο.
   */
  private function hasTranslations(): bool {
    $cached = $this->state->get('unicorn_query_optimizer.has_translations');
    if ($cached !== NULL) {
      return (bool) $cached;
    }

    try {
      $result = $this->database->select('node_field_data', 'n')
        ->condition('n.default_langcode', 0)
        ->range(0, 1)
        ->countQuery()
        ->execute();
      if ($result === NULL) {
        return TRUE;
      }
      $found = (bool) $result->fetchField();
    }
    catch (\Throwable) {
      return TRUE;
    }

    $this->state->set('unicorn_query_optimizer.has_translations', $found);

    return $found;
  }

}
