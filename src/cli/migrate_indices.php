<?php

require __DIR__ . '/kernel.php';

require __DIR__ . '/Exporter.php';

function migrate_indices($offset = 0){
    $already_migrated = [];

    if (file_exists(dirname(__FILE__) . "/migrated_indices.csv")){
        $already_migrated = explode(",", file_get_contents(dirname(__FILE__) . "/migrated_indices.csv"));
    }
    $_current = $count = count($already_migrated) ?? 0;
    $exporter = new Exporter();
    $indices = $exporter->_sql_fetchStocks($_current ?? $offset);

    foreach($indices as $index){
        if (!empty($index['ase_stock_id']) && !in_array($index['ase_stock_id'], $already_migrated)){

            $query = \Drupal::database()->insert('lib_liberal_indices_integration_db');

            $tradeDate = DateTime::createFromFormat("Y-m-d H:i:s", $index['timestamp']);
            $query->fields([
                'instrCode' => $index['ase_stock_symbol'],
                'tradeDate' => $tradeDate->format("Y-m-d H:i:s"),
                'closePrice' => $index['ase_stock_price_close'],
                'closePrevCloseDelta' => $index['ase_stock_previousClose'],
                'closePrevClosePDelta' => $index['ase_stock_percentDiff'],
                'prevClosePrice' => $index['ase_stock_previousClose'],
                'prevCloseDelta' => $index['ase_stock_diff'],
                'prevClosePDelta' => $index['ase_stock_percentDiff'],
                'closed' => $index['closed'],
                'price' => $index['ase_stock_price_close'],
                'pricePrevClosePriceDelta' => $index['ase_stock_previousClose'],
                'pricePrevClosePricePDelta' => $index['ase_stock_percentDiff'],
                'lowPrice' => $index['ase_stock_low'],
                'highPrice' => $index['ase_stock_high'],
                'openPrice' => $index['ase_stock_price_open'],
                'totalTurnover' => $index['ase_stock_turnOver'],
                'totalVolume' => $index['ase_stock_volume'],
                'capitalization' => $index['ase_stock_mrktCap']
            ]);

            $insert = $query->execute();

            if (!empty($insert)){
                file_put_contents(dirname(__FILE__) . "/migrated_indices.csv", $index['ase_stock_symbol'] . ",", FILE_APPEND);
                echo $insert . $index['ase_stock_symbol'] . $index['timestamp'] . PHP_EOL;
            }

            echo $_current++ ."/". count($indices)+$offset . PHP_EOL;
            $count++;
            if ($count >= $offset + 100){
                migrate_indices($count);
            }
        }
    }
}
migrate_indices();