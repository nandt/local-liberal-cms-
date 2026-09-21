<?php

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
* Implements hook_token_info().
*/
function liberal_custom_content_token_info() {
  $info = [];

  $info['types']['liberal_custom_content'] = [
    'name' => t('Liberal Custom Tokens'),
    'description' => t('Liberal Custom Tokens.'),
  ];

  $info['tokens']['liberal_custom_content']['oblation-url'] = [
    'name' => t("Oblation Url."),
    'description' => t('Oblation Url.'),
    'type' => 'url',
  ];

  return $info;
}

/**
* Implements hook_tokens().
*/
function liberal_custom_content_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  if ($type == 'liberal_custom_content' && (!empty($data['taxonomy_term']) && $data['taxonomy_term']->bundle() == 'oblations')) {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'oblation-url':
          $term = $data['taxonomy_term'];
          $tid = $term->id();
          $url = Url::fromRoute('view.oblation_microsite.page', ['taxonomy_term' => $tid], ['absolute' => TRUE])->toString();
          $replacements[$original] = $url;
          break;
      }
    }
  }
  return $replacements;
}
