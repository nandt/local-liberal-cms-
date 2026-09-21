<?php

namespace Drupal\liberal_sitemap_generator\Services;

class IndexXrimatistirioSitemapLinks {

  public function getIndexXrimatistirioSitemapLinks() {

    $query = \Drupal::entityTypeManager()->getStorage('file')->getQuery()
      ->condition('filename', ['sitemap_stocks.xml', 'sitemap_indexes.xml'], 'IN')
      ->condition('filemime', 'application/xml', '=')
      ->condition('uri', 'public://%', 'LIKE')
      ->accessCheck(FALSE)
      ->sort('fid', 'ASC');

    $results = $query->execute();
    return $results;

  }

}
