<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Schema;

use Drupal\unicorn_core\Schema\BaseSchema;

class Schema extends BaseSchema {
  /**
   * @return array<string, mixed>
   */
  protected function schema(): array {
    return [
      'unicorn_advertising_tools' => [
        'description' => 'All data relevant to Advertising Tools',
        'fields' => [
          'entity_id' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 0,
            'description' => 'Entity ID',
          ],
          'impressions' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 0,
            'description' => 'Impressions',
          ],
          'target_impressions' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 0,
            'description' => 'Target Impressions',
          ],
          'priority' => [
            'type' => 'int',
            'size' => 'small',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 1,
            'description' => 'Priority',
          ],
          'clicks' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 0,
            'description' => 'Clicks',
          ],
          'start_date' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 0,
            'size' => 'big',
            'description' => 'Start Date (timestamp)',
          ],
          'end_date' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => TRUE,
            'default' => 0,
            'size' => 'big',
            'description' => 'End Date (timestamp)',
          ],
          'promoted_date' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => FALSE,
            'default' => 0,
            'size' => 'big',
            'description' => 'Date article was added to promoted (timestamp)',
          ],
          'history_date' => [
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => FALSE,
            'default' => 0,
            'size' => 'big',
            'description' => 'Date article was moved to History (timestamp)',
          ],
          'state' => [
            'description' => 'The status of the article',
            'type' => 'int',
            'size' => 'small',
            'not null' => TRUE,
            'default' => 0,
          ],
        ],

        'primary key' => ['entity_id'],

        'indexes' => [
          'state' => ['state'],
          'adtools_dates' => ['end_date'],
          'adtools_impressions' => ['impressions', 'target_impressions'],
        ],
      ],
    ];
  }

}
