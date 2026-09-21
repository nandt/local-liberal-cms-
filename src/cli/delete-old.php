<?php

//require __DIR__ . '/kernel.php';
require __DIR__  . '/Mapper.php';

ini_set('memory_limit', '2G');

function main() {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $nids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'article_liberal', '=')
    ->condition('created','1651363200', '<=')
    ->execute();

  $counter = 0; $countall = count($nids);
  foreach (array_chunk($nids, 40) as $chunk) {

    $counter += 40;
    print $counter."/$countall\n";
    $nodes = $storage->loadMultiple($chunk);
    $storage->delete($nodes);
    unset($nodes);
  }
}

main();
