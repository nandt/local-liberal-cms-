<?php
/**
 * Run this command with drush php:script
 * example: drush php:script fix_body_images.php
 * This will bootstrap Drush / Drupal and run it like a drush command
 */

// require __DIR__ . '/../kernel.php';

// remove if requiring kernel.php
const ROOT = '/opt/drupal';

use Drupal\node\Entity\Node;
use Drupal\Core\File\FileSystemInterface;

$first_nid = 0;
$fixed = [];
$fixed_append = [];

if(file_exists(ROOT . "/migration/tmp_fixed_nodes.txt")){
  $fixed = explode(",",file_get_contents(ROOT . "/migration/tmp_fixed_nodes.txt"));
}

$query = \Drupal::entityQuery('node')
  ->condition('type', 'article_liberal') //specify results to return
  ->condition('body', '%ckfinderIMG/userfiles%', 'LIKE')
  ->condition('nid', $first_nid, '>');

$nids = $query->execute();
$count = 0;
foreach ($nids as $nid) {
  if (in_array($nid, $fixed)){
    continue;
  }
  $node = Node::load($nid);
  if (strlen($node->body->value) == 0) {
      continue;
  }
  list($changesImgs, $node->body->value) = getImgs($node->body->value, $node->id());
  echo $nid . " Imgs: ".($changesImgs ? "Changed":"None") . PHP_EOL;
  $node->save();
  $fixed_append[] = $nid;
  sleep(2);
  if ($count == 10){
    file_put_contents(ROOT . "/migration/tmp_fixed_nodes.txt", implode(",",$fixed_append) . ',', FILE_APPEND);
    $count = 0;
    $fixed_append = [];
  }
  $count++;
}
echo $count.PHP_EOL;


function getImgs($html, $nid){
  $doc = new DOMDocument();
  // remove the doctype declaration, head and body, supress warnings
  @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

  $imgs = $doc->getElementsByTagName('img');
  $foundImgs = false;

  foreach ($imgs as $img) {
    if (checkIfIsImage($img->getAttribute('src'))) {
      $src = $img->getAttribute('src');

      // only make changes if image is ckfinderIMG/userfiles
      if(str_contains($src, '/ckfinderIMG/userfiles')) {
      $fix_src = str_replace('/ckfinderIMG/userfiles','', $src);
      $data = file_get_contents("http://old.liberal.gr" . $fix_src);

      // file get contents failed, log it
      if($data === false) {
        \Drupal::logger('fix_body_images')->error('Error file_get_contents failed in nid: @nid',
        array(
            '@nid' => $nid,
        ));
      }

      // dont do changes if php couldn't get the image
      if(!empty($data)) {
        $foundImgs = true;
        $filename = basename($img->getAttribute('src'));

        // remove % from filename
        $filename = str_replace("%", "", $filename);

        // transliterate and other things
        $transliterated_filename = sanitizeFilename($filename);

        // if filename still too big, just trim it
        $transliterated_filename = limit_filename_length($transliterated_filename, 50);

        // upload each image to S3
        $image_url = upload_nodeImage($transliterated_filename, $data, $nid);
        $img->setAttribute('src', $image_url);
      }
    }
    }
  }

  //return $doc->saveHTML($doc->documentElement);
  return [$foundImgs, $doc->saveHTML($doc->documentElement)];
}

/**
 * Limit the File Name Length
 */
function limit_filename_length($filename, $length)
{
  if (strlen($filename) < $length) {
          return $filename;
  }

  $ext = '';
  if(strpos($filename, '.') !== FALSE) {
      $parts = explode('.', $filename);
      $ext = '.'.array_pop($parts);
      $filename = implode('.', $parts);
  }

  return substr($filename, 0, ($length - strlen($ext))).$ext;
}

  // Transliterate the filename etc before upload
 function sanitizeFilename($filename) {
    $filename = \Drupal::service('transliteration')->transliterate($filename);
    // Replace whitespace.
    $filename = str_replace(' ', '-', $filename);
    // Remove remaining unsafe characters.
    $filename = preg_replace('![^0-9A-Za-z_.-]!', '', $filename);
    // Remove multiple consecutive non-alphabetical characters.
    $filename = preg_replace('/(_)_+|(\.)\.+|(-)-+/', '\\1\\2\\3', $filename);
    // Force lowercase to prevent issues on case-insensitive file systems.
    $filename = strtolower($filename);

    return $filename;
  }

function upload_nodeImage($filename, $data, $nid){

  try{
  $file_system = \Drupal::service('file_system');
  $directory = 'public://tinymceUploads';

    // check if directory is writeable / create if it does not exist
  $file_system->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

  // create the file and save it unmanaged
   $filepath = $file_system->saveData($data, $directory . '/' . $filename , FileSystemInterface::EXISTS_REPLACE);

   if($filepath !== false) {
      $filepath = \Drupal::service('file_url_generator')->generateAbsoluteString($filepath);
   }
    else {
      \Drupal::logger('fix_body_images')->error('Error saving image in nid: @nid',
      array(
          '@nid' => $nid,
      ));
    }

    return $filepath;

  } catch(\Exception $ex){
      throw $ex;
  }
}

function checkIfIsImage($data){
    return strpos($data, ".jpg") !== false || strpos($data, ".jpeg") !== false || strpos($data, ".png") !== false || strpos($data, ".gif") !== false || strpos($data, ".JPG") !== false || strpos($data, ".JPEG") !== false || strpos($data, ".PNG") !== false || strpos($data, ".GIF") !== false;
}

function checkImageFileType($filename){

  $ext = pathinfo($filename, PATHINFO_EXTENSION);
  return $ext;
}

function checkIfAnchorIsAlreadyS3($path){
  return strpos($path, getenv("CDN_URL")) !== false;
}
