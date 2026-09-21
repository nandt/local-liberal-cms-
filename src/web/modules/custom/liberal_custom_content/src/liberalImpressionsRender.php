<?php

namespace Drupal\liberal_custom_content;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Render\Markup;

/**
 * Implements trusted post render callbacks
 *
 * @internal
 */
class liberalImpressionsRender implements TrustedCallbackInterface {

  /**
   * Post render function to add the impression image if applicable
   */
  public static function addImpressionsImage($element, $field) {
    if (!empty($element)) {
      $html = $element->__toString();
      $impression_img = $field['#entity']->field_kodikas_impression_image->value;
      $html .= $impression_img;
      $element = Markup::create($html);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'addImpressionsImage',
    ];
  }

}
