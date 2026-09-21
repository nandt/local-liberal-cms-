<?php

require __DIR__ . '/kernel.php';
require __DIR__ . '/Mapper.php';

use Drupal\node\Entity\Node;

function fetch_drupal(int $author_id = null)
{
    $query = \Drupal::entityQuery('node')->condition('type', 'article_liberal')->condition('field_arthrografos', $author_id, '=');
    $nids = $query->execute();
    return $nids;
}

function main(): void {
    $transfer_ids = array(
        83282 => 83357, # Θανάσης Μαυρίδης
        83294 => 83358, # Σάκης Μουμτζής
        83283 => 83429, # Βίβιαν Ευθυμιοπούλου
        83295 => 83447, # Κωνσταντίνος Χαροκόπος
        83322 => 84077, # Κατερίνα Γαλανού
        83284 => 84664  # Δημήτρης Καμπουράκης
    );

    foreach ($transfer_ids as $old_id => $new_id) {
        $nids = fetch_drupal($old_id);
        $counter = 0;
        foreach ($nids as $nid) {
            $node = Node::load($nid);
            $node->set('field_arthrografos', $new_id);
            $node->save();
            $counter++;
            if ($counter % 10 == 0) {
                echo "Transferring...\n";
            }
        }
        echo "Transfered " . $counter . " articles for author " .$new_id . "\n";
    }
}

main();