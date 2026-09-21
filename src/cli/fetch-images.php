<?php

/**
 * Creates map.image_download.json photo_url.tsv
 */

require __DIR__ . '/kernel.php';
require __DIR__ . '/Exporter.php';
require __DIR__ . '/Mapper.php';

function main() {
  $exporter = new Exporter(); // exports data from the old db
  $mapper = new Mapper(); // maps old with new IDs in the filesystem
  $content = $mapper->map_content();
  if (!empty($content)) {
    $last_key = array_key_last($content); // get the last key of the migrated content
    echo "Continuing from ". $last_key ."\n";
  } else {
      $last_key = "0";
  }

  $counter = 0; $countall = $exporter->content_count($last_key);
  if (($imageUrl_fs = fopen('../migration/photo_url.tsv', 'a')) === false) // save all image urls in this file
    throw new Exception("Cannot open file");
  while ($row = $exporter->content_fetch($last_key)) {
    if (!empty($row->realimg))
      if (!isset($mapper->map_image_download()[$row->realimg])) {
        echo "FETCHING_NEW_IMAGE {$row->realimg}:{$row->id} ".(++$counter)."/$countall\n";
        $mapper->map_image_download()[$row->realimg] = $row->id;
        if (fwrite($imageUrl_fs, "https://media.liberal.gr/" . $row->realimg . "\n") === false)
          throw new Exception("Cannot write to file");
      } else echo "IMAGE_ALREADY_FETCHED {$row->realimg}:{$row->id} ".(++$counter)."/$countall\n";
    else echo "EMPTY_IMAGE {$row->realimg}:{$row->id} ".(++$counter)."/$countall\n";
  }
  fclose($imageUrl_fs);
  echo PHP_EOL;
  $mapper->commitImageDownloaded();
}

main();
