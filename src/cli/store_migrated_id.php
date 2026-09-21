<?php

require __DIR__ . '/kernel.php';
require __DIR__ . '/Mapper.php';

use Drupal\node\Entity\Node;

function fetch_drupal(int $start_from = null)
{
    if ($start_from) {
        $first_nid = $start_from;
    } else {
        $first_nid = 0;
    }
    $query = \Drupal::entityQuery('node')->condition('type', 'article_liberal')->condition('nid', $first_nid, '>')->sort('nid', 'ASC');
    $nids = $query->execute();
    return $nids;
}

function main(): void {
    // Find the last node processed
    $file_name = "../migration/last_fixed_migrated_id";
    if (!file_exists($file_name)) {
        touch($file_name);
    }
    if (($start_from = file_get_contents($file_name)) === false)
        throw new Exception("Cannot read file: " . $file_name);


    $mapper = new Mapper();
    $content = $mapper->map_content(); // prefetch the content

    foreach ($content as $old_id => $new_id) {
        if ($old_id < $start_from) {
            continue;
        }

        $node = Node::load($new_id);
        $node->set('field_migrated_id', $old_id);
        $node->save();

        echo "Stored old id " . $old_id . " for node " . $new_id . "\n";
        if (file_put_contents($file_name, $old_id) === false)
            throw new Exception("Cannot save file: $file_name");
    }
}

main();