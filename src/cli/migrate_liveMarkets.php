<?php

require __DIR__ . '/kernel.php';

require __DIR__ . '/Exporter.php';

use Drupal\node\Entity\Node;

function addNode(array $liveMarket): void
{
    try{
        if (!empty($liveMarket['liveMarket_descr'])) {
            $dateTime = $liveMarket['liveMarket_date'];
            $str = str_replace('&nbsp;', ' ', strip_tags(trim($liveMarket['liveMarket_short_descr'])));
            $new = html_entity_decode($str);
            $new = mb_substr($new, 0, strlen($new)>255?200:strlen($new)) . "...";
            $data = [
                'type' => 'article_liberal',
                'title' => $new,
                'status' => 1,
                'langcode' => 'el', // need to choose between el or en
                'uid' => 1, // need to related with the correct user
                'created' => $dateTime->format('U'),
                'changed' => $dateTime->format('U'),
                'body' => [
                    'value' => trim($liveMarket['liveMarket_descr']),
                    'summary' => trim($liveMarket['liveMarket_short_descr']),
                    'format' => 'basic_html'
                ],
                'field_show_summary' => ['value' => 1],
                'field_title' => [
                    'value' => !empty($liveMarket['liveMarket_short_descr']) ? $new : 'article_liberal',
                    'format' => 'basic_html'
                ],
                'field_introduction' => ['value' => trim($liveMarket['liveMarket_descr']), 'format' => 'basic_html'],
                'field_teleytaia_enimerosi' => [
                    'value' => $dateTime->format('Y-m-d\TH:i:s')
                ],
                'field_liberal_category' => ['target_id' => 928]
            ];

            $node = Node::create($data);
            $node->enforceIsNew(false);
            $node->save();
            unset($node, $dateTime, $str, $new, $data);
        }
    } catch(Exception $ex){
        file_put_contents("../migration/migrated_liveMarkets.errors",  "[liveMarketId: ".$liveMarket['liveMarket_id']. "] - ".$ex->getMessage().PHP_EOL."#".$liveMarket['liveMarket_short_descr']."#".PHP_EOL, FILE_APPEND);
        throw $ex;
    }
}

/**
 * @throws Exception
 */
function migrate_liveMarkets(int $offset, int $limit)
{
    $exporter = new Exporter();
    $liveMarkets = $exporter->_sql_fetchLiveMarkets($offset, $limit);

    if (!empty($liveMarkets)) {
        foreach ($liveMarkets as $liveMarket) {
            addNode($liveMarket);
        }
        echo $offset + $limit . " - " . $limit . PHP_EOL;
        file_put_contents("../migration/migrated_liveMarkets.log", $offset + $limit . "," . $limit);
        unset($liveMarkets);
        migrate_liveMarkets($offset + $limit, $limit);
    }
}

$already_migrated = null;

if (file_exists("../migration/migrated_liveMarkets.log")) {
    $input = file_get_contents("../migration/migrated_liveMarkets.log");
    $already_migrated = explode(",", trim($input));
    migrate_liveMarkets((int)$already_migrated[0], (int)$already_migrated[1]);
} else {
    migrate_liveMarkets(0, 100);
}

