<?php

require __DIR__ . '/kernel.php';
require __DIR__ . '/Exporter.php';
require __DIR__ . '/Importer.php';
require __DIR__ . '/Mapper.php';

function main() {
  $exporter = new Exporter(); // exports data from the old db
  $mapper = new Mapper(); // maps old with new IDs in the filesystem
  $content = $mapper->map_content(); // prefetch the content
  if (!empty($content)) {
    $last_key = array_key_last($content); // get the last key of the migrated content
    echo "Continuing from ". $last_key ."\n";
  } else {
    $last_key = "0";
  }
  $importer = new Importer($mapper); // imports data in the new db
  $counter = 0;
  $countall = $exporter->content_count($last_key); // total rows of articles. this query is not cached
  while ($row = $exporter->content_fetch($last_key)) {
    if (isset($mapper->map_content()[$row->id])) {
      echo "ARTICLE ALREADY MIGRATED {$row->id}:{$mapper->map_content()[$row->id]} ".(++$counter)."/$countall\n";
      continue;
    }
    $stocks=$exporter->fetchStocksForArtcile($row->id);
    if (($node = $importer->node_create($row, $stocks)) !== null)
      echo "ARTICLE_SAVED {$row->id}:{$node->id()} ".(++$counter)."/$countall\n";
    else
      echo "ARTICLE_SKIPPED {$row->id}:? ".(++$counter)."/$countall\n";
    $mapper->commitContent();
  }
}

function test_create() {
  $taxonomy = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $taxonomy->create([
    'vid' => 'category',
    'name' => 'test',
  ])->save();
}

function test_load() {
  $taxonomy = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    #'tid' => 176,
    'vid' => 'category',
    'name' => 'test',
  ]);
  #$taxonomy[176]->name1 = 'a';
  print json_encode($taxonomy[176]->name->value).PHP_EOL;
}

#test_load();
main();



