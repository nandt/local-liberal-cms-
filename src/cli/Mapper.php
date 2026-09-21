<?php

/**
 * Links entity IDs between old and new db.
 */
class Mapper {

  private string $file_map_content;
  private string $file_map_content_bak;
  private string $file_map_image_download;
  private string $file_map_image_upload;

  /**
   * Used to choose between inserting or updating a migrated article.
   * @return array<int, int> oldContentID_map_newNodeID
   */
  public function &map_content() {
    if ($this->data_content === null) {
      $bak_file = stat($this->file_map_content_bak);
      if ($bak_file === false) {
        $this->data_content = $this->_loadFile($this->file_map_content);
      } else {
        $size = stat($this->file_map_content)['size'];
        $bak_size = $bak_file['size'];
        if ($size >= $bak_size) {
          $this->data_content = $this->_loadFile($this->file_map_content);
        } else {
          $this->data_content = $this->_loadFile($this->file_map_content_bak);
        }
      }
    }
    return $this->data_content;
  } private mixed $data_content = null;

  /**
   * Used to have photo_url.tsv values without duplicates.
   * @return array<string, int> imageName_map_oldContentID
   */
  public function &map_image_download() {
    if ($this->data_image_download === null)
      $this->data_image_download = $this->_loadFile($this->file_map_image_download);
    return $this->data_image_download;
  } private mixed $data_image_download = null;

  /**
   * Used to upload images with upload.php without duplicates.
   * @return array<string, int> imageName_map_fileID
   */
  public function &map_image_upload() {
    if ($this->data_image_upload === null)
      $this->data_image_upload = $this->_loadFile($this->file_map_image_upload);
    return $this->data_image_upload;
  } private mixed $data_image_upload = null;

  public function __construct() {
    $this->file_map_content = ROOT . '/migration/map.content.json';
    $this->file_map_content_bak = $this->file_map_content.".bak";
    $this->file_map_image_download = ROOT . '/migration/map.image_download.json';
    $this->file_map_image_upload = ROOT . '/migration/map.image_upload.json';
  }

  private function _touchFile($file) {
    if (!file_exists($file)) {
      touch($file);
      //file_put_contents($file, '{}');
    }
  }

  /**
   * Return object data from $file.
   * @param $file
   * @return mixed
   * @throws Exception
   */
  private function _loadFile($file) {
    $this->_touchFile($file);
    if (($contents = file_get_contents($file)) === false)
      throw new Exception("Cannot read file: $file");
    return $this->_decode($contents);
  }

  /**
   * Save in $file object $data.
   * @param $file
   * @param $data
   * @throws Exception
   */
  private function _saveFile($file, $data) {
    $this->_touchFile($file);
    if ($data === []) $data = new stdClass();
    if ($file == $this->file_map_content) {
        copy($file, $this->file_map_content_bak);
    }
    if (file_put_contents($file, $this->_encode($data)) === false)
      throw new Exception("Cannot save file: $file");
  }

  private function _encode($x) {return json_encode($x, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);}
  private function _decode($x) {return json_decode($x, true);}

  /**
   * Save memory in cli/*_map.json files after handling the objects.
   * Must be called before exit() in order *_content() to be saved in the filesystem.
   */
  public function commit() {
    $this->commitContent();
    $this->commitImageDownloaded();
    $this->commitImageUpload();
  }

  public function commitContent() {
    $this->_saveFile($this->file_map_content, $this->map_content());
  }

  public function commitImageDownloaded() {
    $this->_saveFile($this->file_map_image_download, $this->map_image_download());
  }

  public function commitImageUpload() {
    $this->_saveFile($this->file_map_image_upload, $this->map_image_upload());
  }

}