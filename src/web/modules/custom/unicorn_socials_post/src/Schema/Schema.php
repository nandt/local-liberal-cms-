<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Schema;

use Drupal\unicorn_core\Schema\BaseSchema;

class Schema extends BaseSchema {
    /**
     * @return array<string, mixed>
     */
    protected function schema(): array {
      return [
        'unicorn_social_sharing' => [
          'description' => 'All data relevant to Social Media Sharing',
          'fields' => [
            'entity_id' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'description' => 'Entity ID',
            ],
            'facebook_times_shared' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'description' => 'How many times the article has been shared on Facebook so far',
            ],
            'facebook_last_sharing_date' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'size' => 'big',
              'description' => 'The date the article was shared on Facebook most recently (timestamp)',
            ],
            'x_times_shared' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'description' => 'How many times the article has been shared on X so far',
            ],
            'x_last_sharing_date' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'size' => 'big',
              'description' => 'The date the article was shared on X most recently (timestamp)',
            ],
            'notification_times_sent' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'description' => 'How many push notifications have been sent for this article so far',
            ],
            'last_notification_date' => [
              'type' => 'int',
              'unsigned' => TRUE,
              'not null' => TRUE,
              'default' => 0,
              'size' => 'big',
              'description' => 'The date the last push notification was sent for this article (timestamp)',
            ],
          ],
          'primary key' => ['entity_id'],
          'indexes' => [
            'facebook_last_sharing_date' => ['facebook_last_sharing_date'],
            'x_last_sharing_date' => ['x_last_sharing_date'],
            'last_notification_date' => ['last_notification_date'],
          ],
        ],
      ];
    }
}
