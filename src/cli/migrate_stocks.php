<?php

use Drupal\Core\Database\Database;

require __DIR__ . '/kernel.php';

require __DIR__ . '/Exporter.php';


function migrate_stocks($offset = 0){
    $already_migrated = [];

    if (file_exists(dirname(__FILE__) . "/migrated_stocks.csv")){
        $already_migrated = explode(",", file_get_contents(dirname(__FILE__) . "/migrated_stocks.csv"));
    }
    $_current = $count = count($already_migrated) ?? 0;
    $exporter = new Exporter();
    $stocks = $exporter->_sql_fetchStocks($_current ?? $offset);

    foreach($stocks as $stock){
        if (!empty($stock['ase_stock_id']) && !in_array($stock['ase_stock_id'], $already_migrated)){
            $connection = \Drupal::service('database');
            $insert = $connection->insert('liberal_stocks_integration_db')
                ->fields([
                'instrCode' => $stock['ase_stock_symbol'],
                'tradeDate' => $stock['timestamp'],
                'closePrice' => $stock['ase_stock_price_close'],
                'closePrevCloseDelta' => $stock['ase_stock_previousClose'],
                'closePrevClosePDelta' => $stock['ase_stock_percentDiff'],
                'prevClosePrice' => $stock['ase_stock_previousClose'],
                'prevCloseDelta' => $stock['ase_stock_diff'],
                'prevClosePDelta' => $stock['ase_stock_percentDiff'],
                'closed' => $stock['closed'],
                'price' => $stock['ase_stock_price_close'],
                'pricePrevClosePriceDelta' => $stock['ase_stock_previousClose'],
                'pricePrevClosePricePDelta' => $stock['ase_stock_percentDiff'],
                'lowPrice' => $stock['ase_stock_low'],
                'highPrice' => $stock['ase_stock_high'],
                'openPrice' => $stock['ase_stock_price_open'],
                'totalTurnover' => $stock['ase_stock_turnOver'],
                'totalVolume' => $stock['ase_stock_volume'],
                'capitalization' => $stock['ase_stock_mrktCap']
            ])->execute();
            if (!empty($insert)){
                file_put_contents(dirname(__FILE__) . "/migrated_stocks.csv", $stock['ase_stock_symbol'] . ",", FILE_APPEND);
                echo $insert . $stock['ase_stock_symbol'] . $stock['timestamp'] . PHP_EOL;
            }

            echo $_current++ ."/". count($stocks)+$offset . PHP_EOL;
            $count++;
            if ($count >= $offset + 100){
                migrate_stocks($count);
            }
        }
    }
}
migrate_stocks();