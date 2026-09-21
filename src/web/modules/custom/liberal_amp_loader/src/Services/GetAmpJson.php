<?php

namespace Drupal\liberal_amp_loader\Services;

use Drupal\image\Entity\ImageStyle;

use Drupal\Core\Url;

class GetAmpJson {

  public function __construct(
    private readonly AmpNextPageNids $ampNextPageNids,
  ) {
  }

  public function getRenderedAmpJson($current_nid) {
    // Get all nodes for the loader
    $loading_nids = $this->ampNextPageNids->getLoadingNids((int) $current_nid);

    $items = self::createAmpJson($loading_nids['with_current_nid'], $current_nid);
    $items_json = self::encodeAmpJson($items);

    return $items_json;
  }

  public static function renderAmpArrayJsonLd($jsonld) {
    return [
      '#type' => 'html_tag',
      '#tag' => 'amp-next-page',
      'child' => [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => $jsonld,
        '#attributes' => ['type' => 'application/json'],
      ],
    ];
  }

  /**
 * {@inheritdoc}
 */
  public static function encodeAmpJson(array $items) {
    // Render the JSON LD otherwise return nothing
    if (!empty($items)) {
      return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
    else {
      return '';
    }
  }

  public static function getAmpJsonData($nids) {
    $connection = \Drupal::database();
    $query = $connection->select('node_field_data', 'nfd');

    // when using multiple joins you need to write them line by line because join returns the alias
    $query->leftjoin('node__field_kentriki_fotografia', 'nkf', 'nfd.nid = nkf.entity_id');
    $query->leftjoin('file_managed', 'fm', 'nkf.field_kentriki_fotografia_target_id = fm.fid');
    $query
      ->fields('nfd', ['nid'])
      ->fields('nfd', ['title'])
      ->fields('fm', ['uri'])
      ->condition('nfd.nid', $nids, 'IN');

    // Execute the statement
    $executed = $query->execute();

    // Get all the results
    $results = $executed->fetchAllAssoc('nid');

    // sort array again based on original array in php to avoid mysql load
    $nids_sorting = array_combine($nids, $nids);
    $results = array_replace($nids_sorting, $results);

    // optional part to get category image if image empty
    foreach ($results as $result) {
      if (empty($result->uri)) {
        $replace_imgs[] = $result->nid;
      }
    }

    // query to get missing images
    if (!empty($replace_imgs)) {
      $query = $connection->select('node__field_liberal_category', 'lc');
      $query->join('taxonomy_term__field_metatag_image', 'fmi', 'lc.field_liberal_category_target_id = fmi.entity_id');
      $query->join('file_managed', 'fm', 'fmi.field_metatag_image_target_id = fm.fid');
      $query
        ->fields('lc', ['entity_id'])
        ->fields('fm', ['uri'])
        ->condition('lc.entity_id', $replace_imgs, 'IN');

      // Execute the statement
      $executed = $query->execute();
      $results_imgs = $executed->fetchAllAssoc('entity_id');

      // replace all missing images
      foreach ($results_imgs as $key => $results_img) {
        $results[$key]->uri = $results_img->uri;
      }
    }

    return $results;
  }

  public static function createAmpJson($nids, $current_nid) {

    $cid = 'lal_amp_next_page';

    if ($cache = \Drupal::cache()->get($cid)) {
      $render_array = $cache->data;
    }
    else {
      $items = self::getAmpJsonData($nids);
      $render_array = [];

      foreach ($items as $item) {
        if (!empty($item->uri)) {
          $style = ImageStyle::load('amp_thumbnail');

          if (!empty($style)) {
            $image_url = $style->buildUrl($item->uri);
          }
          else {
            $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($item->uri);
          }

          $title = $item->title . ' | Liberal.gr';
          $amp_url = Url::fromRoute('entity.node.canonical', ['node' => $item->nid], ['absolute' => TRUE, 'query' => ['amp' => 1]]);
          $amp_url = $amp_url->toString();

          $render_array[$item->nid] = [
            'url' => $amp_url,
            'title' => $title,
            'image' => $image_url,
          ];
        }
      }

      $render_array = [
        'pages' => $render_array,
      ];

      // cache the results for 180 seconds
      \Drupal::cache()->set($cid, $render_array, \Drupal::time()->getRequestTime() + 180);
    }

    // remove the current nid if it exists after all caching etc has taken place
    unset($render_array['pages'][$current_nid]);

    // reset array keys again to print in json
    $render_array['pages'] = array_values($render_array['pages']);
    return $render_array;
  }

}
