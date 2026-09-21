<?php
use Drupal\taxonomy\Entity\Term;

/*
* Run with drush php:script
* Put csv file into same folder as this script.
* Example ------
* docker exec liberal-cms-drupal drush php:script cli/StocksData/stocks_data_import.php
*/

/** Rerefence header
* 0:"id"
* 1:"code" -> symbol GR
* 2:"name"
* 3:"nameEN"
* 4:"alternative_code" -> Symbol EN
* 5:"greeklish_name"
* 6:"reference_data_type"
*/

const stocksFields = [
  'name',
  'field_stc_company_name',
  'field_stc_company_name_en',
  'field_stc_company_name_greeklish',
  'field_stc_symbol_en'
];

const indicesFields = [
  'name',
  'field_ase_idx_company_name',
  'field_ase_idx_company_name_en',
  'field_ase_idx_company_name_grsh',
  'field_ase_idx_symbol_en'
];

// Open the file for reading
$filepath = $path . "/cli/StocksData/stocks_reference_data.csv";
$iteration = 0;
$created_terms = [];

if (($file = fopen($filepath, "r")) !== FALSE)
{

  $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery();

  $orGroup1 = $query->orConditionGroup()
  ->condition('vid', 'metohes', '=')
  ->condition('vid', 'indices', '=');

  $terms = $query->condition($orGroup1)
  ->execute();

  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($terms);
  \Drupal::entityTypeManager()->getStorage('taxonomy_term')->delete($terms);
  unset($terms);

  // Convert each line into the local $data variable
  while (($data = fgetcsv($file, 0, ",")) !== FALSE)
  {

   // Skip first iteration, its the headers. Also
   if ($iteration !== 0 ) {

    // code is our current name
    $code = $data[1];
    $code_en = $data[4];
    $name = $data[2];
    $name_en = $data[3];
    $greeklish_name = $data[5];
    $type = $data[6];

    if($data[6] != 'INDEX') {
      $operation = "stock";
      $field_names = stocksFields;
      $vid = "metohes";
    }
    else {
      $operation = "index";
      $field_names = indicesFields;
      $vid = "indices";
    }


      $item = Term::create([
        'vid' => $vid,
        'name' => $code,
      ]);

      $item->enforceIsNew();

      $item->set($field_names[0], $code);
      $item->set($field_names[1], $name);
      $item->set($field_names[2], $name_en);
      $item->set($field_names[3], $greeklish_name);
      $item->set($field_names[4], $code_en);

      echo "Creating :" . $item->id() . PHP_EOL .
      $field_names[0] . ": " . $code . PHP_EOL .
      $field_names[1] . ": " . $name . PHP_EOL .
      $field_names[2] . ": " . $name_en . PHP_EOL .
      $field_names[3] . ": " . $greeklish_name . PHP_EOL .
      $field_names[4] . ": " . $code_en . PHP_EOL .
      "-----------------------" . PHP_EOL;
      $item->save();
      $created_terms[] = $item->label() . ' - ' . $item->id() . ' / ' . $operation;
    }

    $iteration++;
}

  // Close the file
  fclose($file);

  // when script finishes
   echo "Created Terms: " . count($created_terms) . PHP_EOL .
   implode(",", $created_terms) . PHP_EOL .
    "-----------------------" . PHP_EOL .
    "Results were also logged in watchdog at stocks_data_import" . PHP_EOL;

    // also log the results in watchdog of Drupal
    \Drupal::logger('stocks_data_import')->info('Created: @created -- @created_terms',
      array(
        '@created' => count($created_terms),
        '@created_terms' => implode(",", $created_terms)
      ));
}
