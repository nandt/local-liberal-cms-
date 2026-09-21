<?php

require __DIR__ . '/kernel.php';
require __DIR__ . '/Mapper.php';

use Drupal\node\Entity\Node;

function main(): void {
    // Fetch the migrated articles map
    $mapper = new Mapper();
    $content = $mapper->map_content(); // prefetch the content

    // Fetch the state of the authors fix
    $file_name = "../migration/last_fixed_timestamp";
    if (!file_exists($file_name)) {
        touch($file_name);
    }
    if (($contents = file_get_contents($file_name)) === false)
        throw new Exception("Cannot read file: " . $file_name);

    // Create connection to SQL Server
    if (($conn = sqlsrv_connect("85.234.145.7", ['TrustServerCertificate' => 1, 'Database' => 'master', 'UID' => 'liberalusr', 'PWD' => 'lib3r@usr423978!', 'CharacterSet' => 'UTF-8', 'MultipleActiveResultSets' => '0'])) === false) {
        throw new Exception("Cannot connect to ms sql server");
    }

    $query = "
        select [content].[id], [content].[date], [content].[cr_dt], [content].[up_dt]
        from [Liberal].[dbo].[content] [content]
        where [content].[id] < ? and [content].[id] > ?
        order by [content].[id] asc";

    $last_key = array_key_last($content);
    $start_key = trim($contents);
    if (($smtm = sqlsrv_prepare($conn, $query, array(&$last_key, &$start_key), array( "Scrollable" => SQLSRV_CURSOR_CLIENT_BUFFERED ))) === false) {
        throw new Exception(json_encode(sqlsrv_errors()));
    }

    // Execute the query
    if (!sqlsrv_execute($smtm)) {
        echo 'Failed to execute prepared query';
        throw new Exception(json_encode(sqlsrv_errors()));
    }

    $timezone_local = new DateTimeZone("Europe/Athens");
    $timezone_utc = new DateTimeZone("UTC");

    while($row = sqlsrv_fetch_object($smtm)) {
        // if article hasn't been migrated skip
        if (!array_key_exists($row->id, $content)) {
            echo "Article " . $row->id . " hasn't been migrated. Skipping\n";
            continue;
        }

        $article_dt = $row->date;
        $article_cr_dt = $row->cr_dt;
        $article_up_dt = $row->up_dt;

        // Fetch local node
        $nid = $content[$row->id];
        $node = Node::load($nid);
        if ($node == null) {
             echo "Node " . $nid . " not found\n";
             break;
        }
        echo "Fixing article " . $row->id . ":" . $nid . "\n";

        $node->set('field_teleytaia_enimerosi', $article_dt->setTimezone($timezone_utc)->format('Y-m-d H:i:s'));
        $node->set('created', $article_cr_dt->getTimestamp());
        $node->save();

        $revision_id = $node->getRevisionId();
        $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($revision_id);
        $revision->setRevisionCreationTime($article_up_dt->getTimestamp());
        $revision->save();

        $node->set('changed', $article_up_dt->getTimestamp());
        $node->save();

        echo "Fixed timestamps for article: " . $nid . "\n";
        if (file_put_contents($file_name, $row->id) === false)
            throw new Exception("Cannot save file: $file_name");
    }
}

main();