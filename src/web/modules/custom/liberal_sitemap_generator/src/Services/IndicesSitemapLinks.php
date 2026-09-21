<?php

namespace Drupal\liberal_sitemap_generator\Services;

use Drupal\Core\Database\Connection;

class IndicesSitemapLinks {

  /**
   * The database connection used.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  public function getIndicesSitemapLinks() {

    $query = $this->connection->select('taxonomy_term_field_data', 'ttfd')
      ->condition('vid', 'indices')
      ->condition('status', 1)
      ->fields('ttfd', ['tid'])
      ->orderBy('tid', 'ASC');
    $query = $query->execute();

    $results = $query->fetchCol();
    return $results;
  }

}
