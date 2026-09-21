<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Injects AMP ad units into rendered article body HTML.
 *
 * Port of the deleted liberal_ads module's addLiberalAmpAds()/liberalAdsUtils.
 *
 * @internal
 */
final class AmpAdInjector implements TrustedCallbackInterface {

  private const int AD_LIMIT = 19;

  /**
   * Paragraph index (1-based) after which each ad slot is placed.
   *
   * @var list<int>
   */
  private const array AD_PLACEMENT = [1, 4, 7, 10, 13, 16];

  /**
   * @var list<array{width: string, height: string, type: string, data-slot: string}>
   */
  private const array AMP_ADS = [
    ['width' => '300', 'height' => '250', 'type' => 'doubleclick', 'data-slot' => '/21772425/AMP_1'],
    ['width' => '300', 'height' => '600', 'type' => 'doubleclick', 'data-slot' => '/21772425/AMP_2'],
    ['width' => '300', 'height' => '250', 'type' => 'doubleclick', 'data-slot' => '/21772425/AMP_3'],
    ['width' => '300', 'height' => '600', 'type' => 'doubleclick', 'data-slot' => '/21772425/AMP_4'],
    ['width' => '300', 'height' => '250', 'type' => 'doubleclick', 'data-slot' => '/21772425/AMP_5'],
    ['width' => '300', 'height' => '250', 'type' => 'doubleclick', 'data-slot' => '/21772425/AMP_6'],
  ];

  /**
   * #post_render callback for the body field: inserts amp-ad units.
   * Targeting JSON comes from $field['#unicorn_ad_targeting'] (set in
   * LiberalAmpLoaderHooks::nodeViewAlter()).
   *
   * @param array<string, mixed> $field
   */
  public static function inject(string $element, array $field): mixed {
    if (empty($element)) {
      return $element;
    }

    $html = '<div class="util-ds">' . $element . '</div>';

    $doc = new \DOMDocument();
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

    $xpath = new \DOMXPath($doc);
    $paragraphs = $xpath->query("/div[contains(@class,'util-ds')]/p");

    if ($paragraphs === FALSE || $paragraphs->length === 0) {
      return $element;
    }

    $paragraphCount = min($paragraphs->length, self::AD_LIMIT);
    $adsToShow = self::numberAdsToShow($paragraphCount);

    $targetingJson = $field['#unicorn_ad_targeting'] ?? NULL;

    for ($i = 0; $i < $adsToShow; $i++) {
      $adNode = self::createAdUnit($doc, self::AMP_ADS[$i], $targetingJson);
      self::placeAdUnit($i, $adNode, $paragraphs);
    }

    return Markup::create($doc->saveHTML($doc->documentElement));
  }

  private static function numberAdsToShow(int $paragraphCount): int {
    $ads = 0;

    foreach (self::AD_PLACEMENT as $placement) {
      if ($placement > $paragraphCount) {
        break;
      }
      $ads++;
    }

    return $ads;
  }

  /**
   * @param array{width: string, height: string, type: string, data-slot: string} $options
   */
  private static function createAdUnit(\DOMDocument $doc, array $options, ?string $targetingJson): \DOMElement {
    $wrapper = $doc->createElement('div');
    $wrapper->setAttribute('class', 'amp-ad-unit');

    $ad = $doc->createElement('amp-ad');
    $ad->setAttribute('width', $options['width']);
    $ad->setAttribute('height', $options['height']);
    $ad->setAttribute('type', $options['type']);
    $ad->setAttribute('data-slot', $options['data-slot']);

    if ($targetingJson !== NULL) {
      $ad->setAttribute('json', $targetingJson);
    }

    $wrapper->appendChild($ad);

    return $wrapper;
  }

  /**
   * @param \DOMNodeList<\DOMNameSpaceNode|\DOMNode> $paragraphs
   */
  private static function placeAdUnit(int $placement, \DOMNode $adNode, \DOMNodeList $paragraphs): void {
    $p = $paragraphs->item(self::AD_PLACEMENT[$placement] - 1);

    if (!$p instanceof \DOMNode) {
      return;
    }

    self::insertAfter($adNode, $p);
  }

  private static function insertAfter(\DOMNode $newNode, \DOMNode $referenceNode): void {
    if ($referenceNode->nextSibling === NULL) {
      $referenceNode->parentNode?->appendChild($newNode);

      return;
    }

    $referenceNode->parentNode?->insertBefore($newNode, $referenceNode->nextSibling);
  }

  public static function trustedCallbacks(): array {
    return ['inject'];
  }

}
