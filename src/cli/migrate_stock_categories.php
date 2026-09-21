<?php

use Drupal\taxonomy\Entity\Term;

require __DIR__ . '/kernel.php';

require __DIR__ . '/Exporter.php';

function migrate_stocks_categories($offset = 0)
{

    $already_migrated = [];

    if (file_exists("../migration/migrated_stock_categories.csv")) {
        $already_migrated = explode(",", file_get_contents("../migration/migrated_stock_categories.csv"));
    }

    $exporter = new Exporter();
    $stockNames = $exporter->_sql_fetchStockNames();

    foreach ($stockNames as $stockName) {
        if (!empty($stockName['ase_stock_symbol']) && !in_array($stockName['ase_stock_symbol'], $already_migrated)) {
            $_aux = array(
                'parent' => array(),
                'name' => trim($stockName['ase_stock_symbol']),
                'vid' => "metohes",
            );

            $term = Term::create($_aux)->save();

            file_put_contents("../migration/migrated_stock_categories.csv", $stockName['ase_stock_symbol'] . ",", FILE_APPEND);
            echo $term->id . ($stockName['ase_stock_symbol']) . PHP_EOL;

        } else {
            echo  ($stockName['ase_stock_symbol'] . " already migrated.") . PHP_EOL;
        }
    }
}

function delete_stock_tags() {
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree("metohes");
    foreach ($terms as $term) {
      //if (empty($term->name) || $term->name == " ") {
      if ($_term = Term::load($term->tid)) {
        // Delete the term itself
        $_term->delete();
        echo $term->tid . " deleted" . PHP_EOL;
      }
      //}
    }
}

delete_stock_tags();
migrate_stocks_categories();
