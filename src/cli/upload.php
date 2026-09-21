<?php

require __DIR__ . '/kernel.php';
require __DIR__ . '/Mapper.php';

use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\Client;

function getImageDirpath() {return '../migration/photo';}

function getImageFilenames() {
  $r = []; $files = scandir(getImageDirpath());
  foreach ($files as $file) {
    if (!in_array($file, ['.', '..']))
      $r[] = $file;
  } return $r;
}

function main() {
  $mapper = new Mapper();
  $count_it = 0; $count_total = count(getImageFilenames());
  foreach (getImageFilenames() as $imageFilename) {
    $count_it++;
    if (isset($mapper->map_image_upload()[$imageFilename])) {
      print "Already Uploaded $count_it/$count_total $imageFilename\n";
    } else {
      print "Uploading $count_it/$count_total $imageFilename\n";
      try {
        $res_upload_fid=upload_Image($imageFilename);
        $mapper->map_image_upload()[$imageFilename] = $res_upload_fid;
      } catch (Throwable $e) {
        print $e->getMessage().PHP_EOL;
      }
      $mapper->commitImageUpload();
    }
  }
}

  function upload_Image($imageFilename) {
    try{
      $data = file_get_contents(getImageDirpath().'/'.$imageFilename);
      $targetDir='s3://photos/'.date("Y-m");
      \Drupal::service('file_system')->prepareDirectory($targetDir, FileSystemInterface::CREATE_DIRECTORY);
      $file_temp = file_save_data($data,  $targetDir.'/'.$imageFilename, FileSystemInterface::EXISTS_REPLACE, );
      echo $file_temp->fid[0]->value." file created.";
      return $file_temp->fid[0]->value;
    }catch(\Exception $ex){
        throw $ex;
    }
  }

main();
