<?php

use Drupal\taxonomy\Entity\Term;

require __DIR__ . '/kernel.php';

require __DIR__ . '/Exporter.php';

function migrate_tags($offset = 0){
  $already_migrated = [];

  if (file_exists("../migration/migrated_tags.csv")){
    $already_migrated = explode(",", file_get_contents("../migration/migrated_tags.csv"));
  }
  $_current = $count = count($already_migrated) ?? 0;
  $exporter = new Exporter();
  $tags = $exporter->_sql_fetchTags($_current ?? $offset);

  foreach($tags as $tag){
    if (!empty($tag['title_el']) && !in_array($tag['title_el'], $already_migrated)){

      $_aux = array(
        'parent' => array(),
        'name' => trim($tag['title_el']) ?? null,
        'vid' => "tags",
      );

      $term = Term::create($_aux)->save();

      if (!empty($term)){
        file_put_contents(dirname(__FILE__) . "/migrated_tags.csv", $tag['title_el'] . ",", FILE_APPEND);
        echo $term->tid . $tag['title_el'] . PHP_EOL;
      }
      $term = $_aux = null;

      echo $_current++ ."/". count($tags)+$offset . PHP_EOL;
      $count++;
      if ($count >= $offset + 100){
        migrate_tags($count);
      }
    }
  }
}

function delete_tags() {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadTree("tags");
  foreach ($terms as $term) {
    //if (empty($term->name) || $term->name == " ") {
    if ($_term = Term::load($term->tid)) {
      // Delete the term itself
      $_term->delete();
      echo $term->tid . " deleted" . PHP_EOL;
    }
    //}
  }
}

//delete_tags();
migrate_tags();
