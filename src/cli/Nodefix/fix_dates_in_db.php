<?php

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/*
* Run with drush php:script
*/

const CHUNK = 40;

$first_nid = 1;

$query = \Drupal::entityQuery('node')
  ->condition('type', 'article_liberal')
  ->condition('field_teleytaia_enimerosi', '%T%', 'NOT LIKE');

$nodes = $query->execute();
$total_nodes = count($nodes);

print "Nodes to process: " . $total_nodes . PHP_EOL;

$chunks = array_chunk($nodes, CHUNK);

// save memory
unset($nodes);

foreach($chunks as $key => $chunk) {
  $loaded_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($chunk);

  foreach($loaded_nodes as $loaded_node) {
    if(!empty($loaded_node->field_teleytaia_enimerosi->value)) {
    $datetime = new DrupalDateTime($loaded_node->field_teleytaia_enimerosi->value);
    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

    // set timezone to storage timezone. Because otherwise site timezone is used
    $datetime->setTimezone($timezone);

    // fixed date for drupal format
    $date_fixed = $datetime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $loaded_node->set('field_teleytaia_enimerosi', $date_fixed);
    $loaded_node->save();
    $total_nodes--;
    print "Updating date of article: " . $loaded_node->id() . PHP_EOL;
    print "Remaining nodes: " . $total_nodes . PHP_EOL;
    print "-------------------------------- " . PHP_EOL;
    }
  }

  // free memory
  unset($chunks[$key]);
}
