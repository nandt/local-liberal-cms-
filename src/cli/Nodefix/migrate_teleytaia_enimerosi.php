<?php
/**
 * Run this command with drush php:script
 * This will bootstrap Drush / Drupal and run it like a drush command
 *
 * If not load and chunk are passed, the default 50 and 1000 will be used
 * Example run
 * drush php:script migrate_teleytaia_enimerosi.php load-nodes=2000 chunk=100
 */

use Drupal\node\NodeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

  const CHUNK = 50;
  const LOAD = 1000;

  $load_nodes = NULL;
  $chunk = NULL;
  $processed = 0;

  if(isset($extra[0]) && str_starts_with($extra[0], "load-nodes")) {
    $load_nodes = explode("=", $extra[0]);
    $load_nodes = (int)$load_nodes[1];
    }
  else {
    $load_nodes = LOAD;
  }

  if(isset($extra[1]) && str_starts_with($extra[1], "chunk")) {
    $chunk = explode("=", $extra[1]);
    $chunk = (int)$chunk[1];
    }
  else {
    $chunk = LOAD;
  }

  $nodes = getNodeIds($load_nodes);
  $chunks = array_chunk($nodes , $chunk);

  foreach ($chunks as $key => $chunk) {
    $processed = processNodes($chunk, count($nodes), $processed);
    // free up memory
    unset($chunks[$key]);
  }

  if(empty($nodes)) {
    print "Found no nodes to process." . PHP_EOL;
  }

  /**
   * @return array
   */

  function getNodeIds($load_nodes) {
    $nodes = [];

    $connection = \Drupal::database();
    $query = $connection->select('node_field_data', 'nfd');
    $query->innerJoin('node__field_teleytaia_enimerosi', 'nfte', 'nfd.nid = nfte.entity_id');

    $query
    ->fields('nfd', ['nid']);
    $query->condition('nfd.type', "article_liberal")
    ->orderBy('nid','ASC')
    ->range(0, $load_nodes);

    $nodes = $query->execute()->fetchCol();
    return $nodes;
  }

  /**
   * Process callback.
   *
   * @param array $chunk
   *   Array of nids to process
   */

  function processNodes($chunk, $total, $processed) {
      $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

      foreach($nodes as $node) {
        if($node instanceof NodeInterface) {
          // if the created date is different than teleutaia_enimerosi date then update it
          $created = $node->getCreatedTime();
          $teleytaia_enimerosi = $node->get('field_teleytaia_enimerosi')->value;

            // Check value is not empty before acting
            if(!empty($teleytaia_enimerosi)) {
              // get UTC default timezone dates are stored (its UTC 0)
              $utc_timezone = new DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
              $teleytaia_enimerosi_datetime = new DateTime($teleytaia_enimerosi, $utc_timezone);
              $teleytaia_enimerosi_timestamp = $teleytaia_enimerosi_datetime->getTimestamp();

              if($created != $teleytaia_enimerosi_timestamp) {
                $node->setCreatedTime($teleytaia_enimerosi_timestamp);
              }

              // Set the date to blank anyway, even if no syncing is needed
              $node->get('field_teleytaia_enimerosi')->removeItem(0);
              $node->save();
              $processed++;
            }
          }
      }

      print "Processed: " . $processed . " / " . $total . " nodes" . PHP_EOL;
      return $processed;
  }
