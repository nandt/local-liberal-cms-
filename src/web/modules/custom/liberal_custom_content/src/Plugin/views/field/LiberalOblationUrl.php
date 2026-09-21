<?php

namespace Drupal\liberal_custom_content\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Attribute\ViewsField;

/**
 * Field handler to print the oblation url.
 */
#[ViewsField("liberal_oblation_url")]
class LiberalOblationUrl extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  #[\Override]
  public function query() {
  }

  /**
   * Render function for the oblaiton_url field.
   *
   * @{inheritdoc}
   */
  #[\Override]
  public function render(ResultRow $values) {
    $tid = $values->tid;

    $link = Url::fromRoute('view.oblation_microsite.page', ['taxonomy_term' => $tid])->toString();
    return $link;
  }

}
