<?php
/**
 * How to run it:
 *
 * Commands:
 * Export from local
 * php dashboard_backup.php backup
 *
 * Get the exported csv and upload it into cli folder of dev app container or
 * push it along with the script which is much easier. Then login to dev app
 * container and run the command below. Please take care to pass the correct filename
 *
 * Import on dev server
 * php dashboard_backup.php import dashboard_backup_1649881541.csv
 */
if (php_sapi_name() !== 'cli') {
  exit;
}
require __DIR__ . '/kernel.php';

use Drupal\node\Entity\Node;

if (count($argv)-1 == 0){
  throw new Exception("No arguments");
}
if ($argv[1] == 'backup'){
  $query = \Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', 'regions');
  $tids = $query->execute();
  foreach($tids as $tid){
    $nids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('field_article_region', $tid)
      ->execute();
    print "Term id :" . $tid.PHP_EOL;
    foreach($nids as $nid){
      print "Node id :" .$nid.PHP_EOL;
      file_put_contents("dashboard_backup_".time().".csv", $tid.";".$nid.";".PHP_EOL, FILE_APPEND);
    }
  }
}

if ($argv[1] == 'import') {
  if (empty($argv[2])) {
    throw new Exception("Missing filename");
  }
  $filename = trim($argv[2]);
  print "Filename to import ". $filename.PHP_EOL;
  if(file_exists($filename)){
    if (($handle = fopen($filename, "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        print "TID: " . $data[0] . " NID:" . $data[1]. PHP_EOL;
        $node = \Drupal\node\Entity\Node::load($data[1]);
        if($node){
          print "Node found Title: ". $node->field_title->value.PHP_EOL;
          $node->set('field_article_region', $data[0]);
          $node->save();
        }
      }
      fclose($handle);
    }
  }
}



