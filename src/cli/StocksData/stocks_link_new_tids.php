<?php

require dirname(__DIR__, 1) . '/Exporter.php';

/*
* !!!! ~~ Run this scrpt after stocks_data_import.php ~~ !!!
* Run with drush php:script
*/

function main() {
  $exporter = new Exporter(); // exports data from the old db
  $first_nid = 1;
  $linked = [];
  $count = 0;
  $tmp_file = "/tmp_linked_stocks.txt";

  // Get all the nodes that are already connected with some stock
  $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
  $query = $storage_handler->getQuery();
  $nids = $query->condition('field_metohi', NULL, 'IS NOT NULL')
  ->condition('field_migrated_id', NULL, 'IS NOT NULL')
  ->condition('nid', $first_nid, '>=')
  ->sort('field_migrated_id', 'ASC')
  ->execute();

  Drupal::logger('stocks_link_new_tids')->info('Linking @nodes nodes', ['@nodes' => count($nids)]);


  if(file_exists(__DIR__ . $tmp_file)){
    $linked = explode(",",file_get_contents(__DIR__ . $tmp_file));
  }

  foreach($nids as $key => $nid) {

  // skip if already processed
  if (in_array($nid, $linked)) {
    echo "Skipping node: " . $nid . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;

    // remove skipped node and free memory
    unset($nids[$key]);
    continue;
  }

  $node = $storage_handler->load($nid);
  $row_id = $node->get('field_migrated_id')->value;

  // get the stocks for the current migrated id
  $stocks = $exporter->fetchStocksForArtcile($row_id);

        $metohes_values = [];
        if (count($stocks) > 0) {
            foreach ($stocks as $stockName) {

            $properties = [
              'name' => $stockName,
              'vid' => 'metohes',
            ];

              $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
              $metohiTid = array_key_first($terms);

              if ($metohiTid !== null) {
                array_push($metohes_values, ['target_id' => strval($metohiTid)]);
              }
            }
        }

        // set the value after linking the stock tids
        $node->set('field_metohi',  $metohes_values);
        $node->save();
        // free memory
        unset($nids[$key]);

        // logging output
        echo "Updating node: " . $node->id() . PHP_EOL;
        echo "Remaining nodes: " . count($nids) . PHP_EOL;
        echo "-----------------------------" . PHP_EOL;
        $linked_append[] = $node->id();

        if($count == 10 || count($nids) <= 1){
          file_put_contents(__DIR__ . $tmp_file, implode(",",$linked_append) . ',', FILE_APPEND);
          $linked_append = [];
          $count = 0;
        }

        $count++;
  }
}

// execute code
main();
