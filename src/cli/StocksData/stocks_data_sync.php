<?php
use Drupal\taxonomy\Entity\Term;

$hostname = getenv('STOCKSDB_HOSTNAME');
$username = getenv('STOCKSDB_USERNAME');
$password = getenv('STOCKSDB_PWD');
$database = getenv('STOCKSDB_DB');

const stocksFields = [
  'code' => 'name',
  'name' => 'field_stc_company_name',
  'nameEN' => 'field_stc_company_name_en',
  'greeklish_name' => 'field_stc_company_name_greeklish',
  'alternative_code' => 'field_stc_symbol_en'
];

const indicesFields = [
  'code' => 'name',
  'name' => 'field_ase_idx_company_name',
  'nameEN' => 'field_ase_idx_company_name_en',
  'greeklish_name' => 'field_ase_idx_company_name_grsh',
  'alternative_code' => 'field_ase_idx_symbol_en'
];

// get the first argumeent and see if the verbose flag
$verbose = isset($extra[0]) && $extra[0] == "showoutput" ? TRUE : FALSE;

try {
    $conn = new PDO("mysql:host=$hostname;dbname=$database;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if($verbose) {
      echo 'Connected successfully!' . PHP_EOL;
    }
}
catch(PDOException $e) {
    die('Error ' . $e->getMessage());
}

$drupal_conn = \Drupal::database();

// get all stocks and indexes
$results = $conn->query("SELECT * FROM reference_data");
$updated_nids = [];
$created_nids = [];

// process each row at a time
while ($data = $results->fetch(PDO::FETCH_ASSOC)) {
  $save_flag = FALSE;

    // code is our current name
    $code = $data['code'];
    $code_en = $data['alternative_code'];
    $name = $data['name'];
    $name_en = $data['nameEN'];
    $greeklish_name = $data['greeklish_name'];
    $type = $data['reference_data_type'];

    if($type != 'INDEX') {
      $term_type = "stock";
      $field_names = stocksFields;
      $vid = "metohes";
    }
    else {
      $term_type = "index";
      $field_names = indicesFields;
      $vid = "indices";
    }

  // check if stock already exists using the name
    $query = $drupal_conn->select('taxonomy_term_field_data', 'ttfd')
    ->condition('vid', $vid)
    ->condition('name', $code)
    ->fields('ttfd',['tid']);
    $query = $query->execute();

    $item = $query->fetchCol();

  if(!empty($item)) {
    $item = array_shift($item);
    $item = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($item);
    $save_flag = checkTermUpdate($item, $data, $field_names);
    $operation = "Updating";

    if($save_flag) {
      $updated_nids[] = $item->label() . ' - ' . $item->id() . ' / ' . $term_type;
    }
  }
  else {
    $operation = "Creating";
    $item = Term::create([
      'vid' =>  $vid,
      'name' => $code,
    ]);

    $save_flag = TRUE;
    $item->enforceIsNew();
  }

   // save the item
    if($save_flag) {
      $item->set($field_names['code'], $code);
      $item->set($field_names['name'], $name);
      $item->set($field_names['nameEN'], $name_en);
      $item->set($field_names['greeklish_name'], $greeklish_name);
      $item->set($field_names['alternative_code'], $code_en);
      $item->save();

      if($verbose) {
        echo $operation . " :" . $item->id() . PHP_EOL .
        $field_names['code'] . ": " . $code . PHP_EOL .
        $field_names['name'] . ": " . $name . PHP_EOL .
        $field_names['nameEN'] . ": " . $name_en . PHP_EOL .
        $field_names['greeklish_name'] . ": " . $greeklish_name . PHP_EOL .
        $field_names['alternative_code'] . ": " . $code_en . PHP_EOL .
        "-----------------------" . PHP_EOL;
      }
    }

    // add to array after saving so we have a tid
    if($operation == 'Creating') {
      $created_nids[] = $item->label() . ' - ' . $item->id() . ' / ' . $term_type;
    }
  }

  // when script finishes
  if($verbose) {
   echo "Created Terms: " . count($created_nids) . PHP_EOL .
    "-----------------------" . PHP_EOL .
    "Updated Terms: " . count($updated_nids) . PHP_EOL .
    "-----------------------" . PHP_EOL .
    "Results were also logged in watchdog at stocks_data_sync" . PHP_EOL;
  }

    // also log the results in watchdog of Drupal
    \Drupal::logger('stocks_data_sync')->info('Created: @created -- @created_nids',
      array(
        '@created' => count($created_nids),
        '@created_nids' => implode(",", $created_nids)
      ));
      \Drupal::logger('stocks_data_sync')->info('Updated: @updated -- @updated_nids',
      array(
        '@updated' => count($updated_nids),
        '@updated_nids' => implode(",", $updated_nids),
      ));

// close the connection
$conn = null;

function checkTermUpdate($item, $db_item, $field_names) {
  $save_flag = false;

  /**
  * Remove the "code" from the check. If this changed for some reason
  * then a new stock / index will be created anyway.
  */
  unset($field_names['code']);

  foreach($field_names as $db_field_name => $field_name) {
    // check if value, if it differs then flag for save
    if(isset($item->$field_name->value) && $item->$field_name->value != $db_item[$db_field_name]) {
      // break from the loop if we find at least one difference
      $save_flag = TRUE;
      break;
    }
  }

  return $save_flag;
}
