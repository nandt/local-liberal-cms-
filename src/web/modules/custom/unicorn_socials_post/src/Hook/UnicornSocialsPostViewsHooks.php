<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for unicorn_social_posts.
 */
class UnicornSocialsPostViewsHooks {

/**
 * @return array<string, mixed>
 */
#[Hook('views_data')]
public function viewsData(): array {

    $data['unicorn_social_sharing']['table'] = [
      'group' => t('Unicorn Socials Post'),
      'base' => [
        'field' => 'entity_id',
        'title' => t('Unicorn Socials Post'),
        'help' => t('Contains data about social sharing metrics.'),
      ],
    ];

    $data['unicorn_social_sharing']['table']['join'] = [
      'node_field_data' => [
        'left_field' => 'nid',
        'field' => 'entity_id',
        'type' => 'LEFT',
      ],
    ];

    $data['unicorn_social_sharing']['entity_id'] = [
      'title' => t('ID'),
      'help' => t('The ID of the entity associated with the social sharing record.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['unicorn_social_sharing']['facebook_times_shared'] = [
      'title' => t('Facebook Times Shared'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['unicorn_social_sharing']['facebook_last_sharing_date'] = [
      'title' => t('Facebook Last Sharing Date'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['unicorn_social_sharing']['x_last_sharing_date'] = [
      'title' => t('X Last Sharing Date'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'standard',
      ]
    ];

    $data['unicorn_social_sharing']['x_times_shared'] = [
      'title' => t('X Times Shared'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    return $data;
  }
}
