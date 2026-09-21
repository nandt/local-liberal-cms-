<?php
require __DIR__ . '/kernel.php';

use Drupal\node\Entity\Node;

$query = \Drupal::entityQuery('node')
  ->condition('status', 1) //published or not
  ->condition('type', 'article_liberal'); //specify results to return
$nids = $query->execute();
$count = 0;
foreach ($nids as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);
  $node->field_subtitle = ['value' => "Δηλώσεις Σόιμπλε", 'format' => 'basic_html'];
  $node->field_energos_ypertitlos->value = 1;
  $node->save();
//  if ($count == 200){
//    exit;
//  }
  $count++;
}
echo $count;
