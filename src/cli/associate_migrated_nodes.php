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
    // Create connection to SQL Server
    if (($conn = sqlsrv_connect("85.234.145.7", ['TrustServerCertificate' => 1, 'Database' => 'master', 'UID' => 'liberalusr', 'PWD' => 'lib3r@usr423978!', 'CharacterSet' => 'UTF-8', 'MultipleActiveResultSets' => '0'])) === false) {
        throw new Exception("Cannot connect to ms sql server");
    }

    // Find the last node processed
    $mapper = new Mapper(); //
    $content = $mapper->map_content(); // prefetch the content
    if (!empty($content)) {
        $last_key = array_key_last($content); // get the last key of the migrated content
        $last_drupal_id = $content[$last_key];
        echo "Continuing from ". $last_drupal_id ."\n";
    } else {
        $last_drupal_id = "0";
    }

    // Fetch missing node ids from drupal
    $nids = fetch_drupal($last_drupal_id);
    echo 'Remaining nodes: ' . count($nids) . "\n";

    // Prepare the SQL Server query
    $title = "";
    $summary = "";
    $query = "
        select id, title_el, short_descr_el, isDraft
        from [Liberal].[dbo].[content]
        where title_el = ?
            and short_descr_el = ?";
    if (($smtm = sqlsrv_prepare($conn, $query, array(&$title, &$summary))) === false) {
        throw new Exception(json_encode(sqlsrv_errors()));
    }

    // For each missing drupal node
    foreach ($nids as $nid) {
        // Fetch the node and update the query variables
        $node = Node::load($nid);
        $title = $node->getTitle();
        $summary = $node->body->summary;

        // Execute the query
        if (!sqlsrv_execute($smtm)) {
            echo 'Failed to execute prepared query';
            throw new Exception(json_encode(sqlsrv_errors()));
        }

        while($row = sqlsrv_fetch_object($smtm)) {
            if (!array_key_exists($row->id, $mapper->map_content())) {
                echo "Mapping " . $row->id . " to " . $nid . "\n";
                $mapper->map_content()[$row->id] = $nid;
                $mapper->commitContent();
                if (!sqlsrv_cancel($smtm)) {
                    echo "Failed to cancel query execution";
                }
                break;
            }
        }
    }
}

main();