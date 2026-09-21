<?php

use Aws\S3\S3Client;
use Drupal\Core\File\FileSystemInterface;
use Drupal\taxonomy\Entity\Term;

require __DIR__ . '/kernel.php';

require __DIR__ . '/Exporter.php';

function migrate_authors(){
  $already_migrated = [];

  if (file_exists("../migration/migrated_authors.csv")){
    $already_migrated = explode(",", file_get_contents("../migration/migrated_authors.csv"));
    
  }


  $exporter = new Exporter();
  $authors = $exporter->_sql_fetchAuthors();

  foreach($authors as $author){
    if (!empty($author['author_name']) && !in_array($author['author_name'], $already_migrated)){
      $file_temp_fid = !empty($author['author_image']) ? upload_authorsProfileImage($author['author_image']):null;

      $_aux = array(
        'parent' => array(),
        'name' => trim($author['author_name'] ?? $author['firstname']. " " . $author['lastname']),
        'vid' => "arthrografos",
      );
      if (!empty($file_temp_fid)){
        $_aux['field_author_profile'] = [[
          'target_id' =>$file_temp_fid,
          'title' => $author['author_friendly'],
          'alt' => $author['author_friendly']
        ]];
      }
      if (!empty($author['author_descr'])){
        $_aux['description'] = trim($author['author_descr']);
      }
      $term = Term::create($_aux);
      $term->save();
      file_put_contents("../migration/migrated_authors.csv", $author['author_name'] . ",", FILE_APPEND);
      echo $term->id().( $author['author_name'] ?? $author['firstname']. " " . $author['lastname'] ).PHP_EOL;

    }
    else{
      echo !empty($author['author_name']) ? ( $author['author_name'] . " already migrated." ).PHP_EOL : "Author name empty ".$author['author_id'];
    }
  }
}
function upload_authorsProfileImage($path){
  try{
    $data = file_get_contents("https://www.liberal.gr/photos/".$path);
    $targetDir='s3://authors';
    \Drupal::service('file_system')->prepareDirectory($targetDir, FileSystemInterface::CREATE_DIRECTORY);
    $file_temp = file_save_data($data, $targetDir.'/'.$path, FileSystemInterface::EXISTS_REPLACE);
    echo $file_temp->fid[0]->value." file created.";
    return $file_temp->fid[0]->value;
  }catch(\Exception $ex){
      throw $ex;
  }
}
function uploadToS3($path){

  $data = file_get_contents("https://www.liberal.gr"."/photos/".$path);
  print_r("https://www.liberal.gr"."/photos/".$path);
  $name = tempnam('tmp', 'php');
  file_put_contents($name, $data);
  if ($data){
    try {
      //Create a S3Client
      $s3Client = new S3Client([
        'version'     => 'latest',
        'region'      => getenv("S3_REGION"),
        'credentials' => [
          'key'    => getenv("AWS_ACCESS_KEY_ID"),
          'secret' => getenv("AWS_SECRET_ACCESS_KEY") ,
        ],
      ]);
      $result = $s3Client->putObject([
        'Bucket' => getenv("BUCKET_NAME"),
        'Key' => ltrim($path, '/'),
        'SourceFile' => $name,
        'ACL' => 'public-read'
      ]);
      $code = $result['@metadata']['statusCode'];
      $uri = $result['@metadata']['effectiveUri'];

      print_r($result);
      if ($code === 200) {
        return $result->fid;
      }

    } catch (S3Exception $e) {
      print_r($e);
      throw $e;
    }
    return false;
  }
}

function delete_authors() {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadTree("arthrografos");
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
function getAuthorTermIDByName($name, $vid = 'arthrografos'){
  if (empty($name) || empty($vid)) {
    return 0;
  }
  $properties = [
    'name' => $name,
    'vid' => $vid,
  ];
  $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
  $term = reset($terms);
  return !empty($term) ? $term->id() : 0;
}

migrate_authors();
