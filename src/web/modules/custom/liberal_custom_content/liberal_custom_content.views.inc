<?php

/**
 * @file
 * Contains views api functions for liberal_custom_content.
 */

/**
 * Implements hook_views_data_alter().
 */
function liberal_custom_content_views_data_alter(array &$data) {
  $data['taxonomy_term_field_data']['liberal_oblation_url'] = [
    'title' => t('Oblation Url'),
    'field' => [
      'title' => t('Oblation Url'),
      'help' => t('Render the oblation url'),
      'id' => 'liberal_oblation_url',
    ],
  ];
}
