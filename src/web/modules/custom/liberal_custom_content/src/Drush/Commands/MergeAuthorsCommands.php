<?php

/* This is a one-shot command to merge certain authors posts */

namespace Drupal\liberal_custom_content\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;

/**
 * A Drush commandfile.
 */

class MergeAuthorsCommands extends DrushCommands {

  const CHUNK = 40;

  use StringTranslationTrait;

  /**
   * @return array
   */

  private static function getAuthorIDs() {
    $author_merge = [];
    $author_2_count = [];

    $author_1_merge = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'arthrografos')
      ->condition('name', 'Δημήτρης Β. Τριανταφυλλίδης')
      ->accessCheck(FALSE)
      ->execute();

    $author_1_merge = array_shift($author_1_merge);

    $author_1_merge_to = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'arthrografos')
      ->condition('name', 'Δημήτρης Τριανταφυλλίδης')
      ->accessCheck(FALSE)
      ->execute();

    $author_1_merge_to = array_shift($author_1_merge_to);

    $author_2 = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'arthrografos')
      ->condition('name', 'Κωνσταντίνος Χαροκόπος')
      ->accessCheck(FALSE)
      ->execute();

    $author_2_dup = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'arthrografos')
      ->condition('name', 'Κ. Χαροκόπος')
      ->accessCheck(FALSE)
      ->execute();

    $author_2_sum = $author_2 + $author_2_dup;

    // find which author has the most articles to decide where to merge to
    foreach ($author_2_sum as $author_2s) {
      $count_nodes = \Drupal::entityQuery('node')
        ->condition('type', 'article_liberal')
        ->condition('field_arthrografos', $author_2s)
        ->accessCheck(FALSE)
        ->execute();

      $author_2_count[$author_2s] = count($count_nodes);
    }

    $author_2_merge_to = array_keys($author_2_count, max($author_2_count));
    $author_2_merge_to = array_shift($author_2_merge_to);
    unset($author_2_count[$author_2_merge_to]);
    $author_2_merge = array_shift(array_keys($author_2_count));

    return $author_merge = [
      'author_1' => [
        'merge_to' => $author_1_merge_to,
        'merge' => $author_1_merge,
      ],
      'author_2' => [
        'merge_to' => $author_2_merge_to,
        'merge' => $author_2_merge,
      ],
    ];
  }

  /**
   * @param array $authors
   */
  private function getToMergeNodes($authors) {
    // flatten the array so it can go through the query
    $flatten_authors = [];

    foreach ($authors as $author) {
      $flatten_authors[] = $author['merge'];
    }

    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('field_arthrografos', $flatten_authors, 'IN')
      ->condition('type', 'article_liberal')
      ->accessCheck(FALSE);

    $merge_nodes = $query->execute();

    return $merge_nodes;
  }

  /**
  * Merge Author Nodes
  */
  #[CLI\Command(name: 'merge:authornodes')]

  public function MergeAuthorNodes() {

    $authors = self::getAuthorIDs();
    $merge_authors = $this->getToMergeNodes($authors);

    $chunks = array_chunk($merge_authors, self::CHUNK);

    $batchBuilder = new BatchBuilder();
    $total_nodes = count($merge_authors);

    foreach ($chunks as $chunk) {
      $batchBuilder->addOperation([self::class, 'processNodes'], [
        $total_nodes,
        $authors,
        $chunk,
      ]);
    }

    if (!empty($chunks)) {
      $batchBuilder
        ->setTitle($this->t('Merge Nodes of Authors Operation'))
        ->setFinishCallback([self::class, 'MergeAuthorsFinished'])
        ->setErrorMessage(t('Batch has encountered an error'));

      batch_set($batchBuilder->toArray());
      drush_backend_batch_process();
    }
  }

  /**
   * Batch process callback.
   *
   * @param int $total_nodes
   *   the total number of nodes to be processed.
   * @param array $chunk
   *   Array of nids to process in batch
   * @param object $context
   *   Context for operations.
   */

  // Always make AddOperation and FinishedCallback public static
  public static function processNodes($total_nodes, $authors, $chunk, &$context) {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

    // initiate counter of processed
    if (!isset($context['results']['count_total'])) {
      $context['results']['count_total'] = 0;
    }

    // initiate total
    if (!isset($context['results']['total_nodes'])) {
      $context['results']['total_nodes'] = $total_nodes;
    }

    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        // check the author of the node and do the necessary merge
        $node_author = $node->field_arthrografos->target_id;

        if (!empty($node_author)) {
          switch ($node_author) {
            case $authors['author_1']['merge']:
              $node->set('field_arthrografos', $authors['author_1']['merge_to']);
              break;

            case $authors['author_2']['merge']:
              $node->set('field_arthrografos', $authors['author_2']['merge_to']);
              break;
          }

          $node->save();
        }
      }

      $context['results']['count_total']++;
    }
  }

  /**
   * @param array<string, mixed> $results
   */
  public static function MergeAuthorsFinished(bool $success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()
        ->addStatus(t('All nodes processed succesfully.'));
    }
    else {
      \Drupal::messenger()->addError('There was an error during the operation');
    }

    \Drupal::messenger()
      ->addMessage(t('Processed @number nodes out of @total_nodes',
        ['@number' => $results['count_total'], '@total_nodes' => $results['total_nodes']]));
  }

}
