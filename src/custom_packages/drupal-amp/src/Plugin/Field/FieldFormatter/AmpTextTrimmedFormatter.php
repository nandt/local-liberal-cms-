<?php

namespace Drupal\amp\Plugin\Field\FieldFormatter;

use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'amp_text_trimmed' formatter.
 *
 * @FieldFormatter(
 *   id = "amp_text_trimmed",
 *   label = @Translation("AMP Trimmed Text"),
 *   description = @Translation("Display AMP Trimmed text."),
 *   field_types = {
 *     "string",
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class AmpTextTrimmedFormatter extends TextTrimmedFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // We're overriding nearly all the parent logic verbatim, except setting
    // the #type to amp_processed_text. The reason we don't call the parent,
    // and then simply override the #type in the render array, is that we want
    // the potential call to \Drupal::service('element_info')->getInfo to only
    // happen _after_ #type has been set to amp_processed_text, so we are
    // assured that we get the complete set of expected #pre_render hooks, and
    // other relevant info.
    // @see \Drupal\amp\Element\AmpProcessedText::getInfo
    $elements = [];

    $render_as_summary = function (&$element): void {
      // Make sure any default #pre_render callbacks are set on the element,
      // because text_pre_render_summary() must run last.
      $element += \Drupal::service('element_info')->getInfo($element['#type']);
      // Add the #pre_render callback that renders the text into a summary.
      $element['#pre_render'][] = [TextTrimmedFormatter::class, 'preRenderSummary'];
      // Pass on the trim length to the #pre_render callback via a property.
      $element['#text_summary_trim_length'] = $this->getSetting('trim_length');
    };

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'amp_processed_text',
        '#text' => NULL,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];

      if ($this->getPluginId() == 'amp_text_summary_or_trimmed' && !empty($item->summary)) {
        $elements[$delta]['#text'] = $item->summary;
      }
      else {
        $elements[$delta]['#text'] = $item->value;
        $render_as_summary($elements[$delta]);
      }
    }

    return $elements;
  }

}
