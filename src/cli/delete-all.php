<?php

require __DIR__ . '/kernel.php';
require __DIR__  . '/Mapper.php';

ini_set('memory_limit', '2G');

function main() {
  $nodes = Drupal::entityTypeManager()->getStorage('node')->loadMultiple(null);
  $counter = 0; $countall = count($nodes);
  foreach ($nodes as $node) {
    print ++$counter."/$countall\n";
    $node->delete();
    unset($node);
  }
}

main();
