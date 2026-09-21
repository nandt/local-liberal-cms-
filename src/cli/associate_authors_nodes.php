<?php

require __DIR__ . '/kernel.php';
require __DIR__ . '/Mapper.php';

use Drupal\node\Entity\Node;

function main(): void {
    // Fetch the migrated articles map
    $mapper = new Mapper();
    $content = $mapper->map_content(); // prefetch the content

    // Fetch the state of the authors fix
    $file_name = "../migration/last_fixed_author";
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
        select [content].[id], [authors].[author_name]
        from [Liberal].[dbo].[content] [content] left join [Liberal].[dbo].[authors] [authors] on [content].[userid] = [authors].[author_id]
        where [content].[userid] > 0 and [content].[id] <= ? and [content].[id] > ?
        order by [content].[id] asc";

    $last_key = array_key_last($content);
    if (($smtm = sqlsrv_prepare($conn, $query, array(&$last_key, &$contents))) === false) {
        throw new Exception(json_encode(sqlsrv_errors()));
    }

    // Execute the query
    if (!sqlsrv_execute($smtm)) {
        echo 'Failed to execute prepared query';
        throw new Exception(json_encode(sqlsrv_errors()));
    }

    while($row = sqlsrv_fetch_object($smtm)) {
        // if article hasn't been migrated skip
        if (!array_key_exists($row->id, $content)) {
            echo "Article " . $row->id . " hasn't been migrated. Skipping";
            continue;
        }
        // Fetch local node
        $nid = $content[$row->id];
        $node = Node::load($nid);
        if ($node == null) {
             echo "Node " . $nid . " not found\n";
             break;
        }
        echo "Fixing article " . $row->id . ":" . $nid . "\n";

        // Find id of author
        $author = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'arthrografos')->condition('name', $row->author_name, '=')->sort('tid', 'ASC')->execute();
        if (count($author) == 0) {
            echo "Author missing from db";
            continue;
        } elseif (count($author) > 0) {
            // update article
            $author_id = array_values($author)[0];
            $node->set('field_arthrografos', $author_id);
            $node->save();
            echo "Fixed author for article: " . $nid . "->" . $row->author_name . "\n";
            if (file_put_contents($file_name, $row->id) === false)
                throw new Exception("Cannot save file: $file_name");
        }
    }
}

main();