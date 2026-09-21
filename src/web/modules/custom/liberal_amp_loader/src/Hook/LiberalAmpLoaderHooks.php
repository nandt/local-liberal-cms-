<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Hook;

use Drupal\amp\Routing\AmpContext;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\liberal_amp_loader\Services\AmpAdInjector;
use Drupal\liberal_amp_loader\Services\AmpAdTargeting;
use Drupal\liberal_amp_loader\Services\AmpAuthorImage;
use Drupal\liberal_amp_loader\Services\AmpImpressionPixel;
use Drupal\liberal_amp_loader\Services\AmpReadMoreBuilder;
use Drupal\liberal_amp_loader\Services\AmpVideoEmbed;
use Drupal\liberal_amp_loader\Services\GetAmpJson;
use Drupal\liberal_custom_content\Utils\liberalCustomContentUtils;
use Drupal\node\NodeInterface;

final readonly class LiberalAmpLoaderHooks {

  public function __construct(
    private ThemeManagerInterface $themeManager,
    private RouteMatchInterface $routeMatch,
    private ConfigFactoryInterface $configFactory,
    private AmpAuthorImage $ampAuthorImage,
    private AmpAdTargeting $ampAdTargeting,
    private AmpReadMoreBuilder $ampReadMoreBuilder,
    private AmpVideoEmbed $ampVideoEmbed,
    private AmpImpressionPixel $ampImpressionPixel,
    private GetAmpJson $getAmpJson,
    private AmpContext $ampContext,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_view_alter().
   *
   * @param array<string, mixed> $build
   */
  #[Hook('node_view_alter')]
  public function nodeViewAlter(array &$build, NodeInterface $node, EntityViewDisplayInterface $display): void {
    $viewMode = $build['#view_mode'] ?? NULL;

    if (!in_array($viewMode, ['amp', 'amp_advertising_article'], TRUE) || $node->getType() !== 'article_liberal') {
      return;
    }

    // AMP output requires full_html.
    if (isset($build['body'][0])) {
      $build['body'][0]['#format'] = 'full_html';
    }

    if (!empty($node->field_kentriki_fotografia->target_id)
      && !empty($build['field_kentriki_fotografia']['#formatter'])
      && in_array($build['field_kentriki_fotografia']['#formatter'], ['image', 'amp_image'], TRUE)) {
      $imageValues = $build['field_kentriki_fotografia'][0]['#item']->getValue();
      $imageValues['alt'] = $node->getTitle();
      $imageValues['title'] = $node->getTitle();
      $build['field_kentriki_fotografia'][0]['#item']->setValue($imageValues);
    }

    $build += $this->ampAuthorImage->build($node);

    $impressionPixel = $this->ampImpressionPixel->build($node);

    if ($impressionPixel !== NULL) {
      $build['unicorn_impression_pixel'] = $impressionPixel;
    }

    if ($this->ampVideoEmbed->isApplicable($node)) {
      $videoEmbed = $this->ampVideoEmbed->build($node);

      if ($videoEmbed !== NULL) {
        $build['unicorn_video_embed'] = $videoEmbed;
      }
    }

    if ($viewMode !== 'amp' || !empty($node->get('field_no_advertisements')->value) || !isset($build['body'][0])) {
      return;
    }

    // "targeting" wrapper is the schema GAM's amp-ad doubleclick expects.
    $targeting = $this->ampAdTargeting->build($node);
    $build['body']['#unicorn_ad_targeting'] = json_encode(['targeting' => $targeting], JSON_UNESCAPED_UNICODE);
    $build['body']['#post_render'][] = [AmpAdInjector::class, 'inject'];
  }

  /**
   * Implements hook_entity_view_display_alter().
   *
   * Swaps 'read_more' from raw-<img> 'image' formatter to amp_image.
   *
   * @param array{entity_type: string, bundle: string, view_mode: string} $context
   */
  #[Hook('entity_view_display_alter')]
  public function entityViewDisplayAlter(EntityViewDisplayInterface $display, array $context): void {
    if ($context['entity_type'] !== 'node' || $context['view_mode'] !== 'read_more') {
      return;
    }

    if ($this->themeManager->getActiveTheme()->getName() !== 'liberal_theme_amp') {
      return;
    }

    $component = $display->getComponent('field_kentriki_fotografia');

    if ($component === NULL) {
      return;
    }

    $component['type'] = 'amp_image';
    $component['settings'] = [
      'image_style' => 'liberal_article_image',
      'image_link' => '',
      'layout' => 'responsive',
      'width' => NULL,
      'height' => NULL,
    ];

    $display->setComponent('field_kentriki_fotografia', $component);
  }

  /**
   * @param array<string, mixed> $libraries
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    // update amp-next-page to version 1
    if ($extension == 'amp') {
      if (!empty($libraries["amp.next-page"])) {
        $next_page_options = $libraries["amp.next-page"]["js"]["https://cdn.ampproject.org/v0/amp-next-page-0.1.js"];
        unset($next_page_options['attributes']['custom-template']);
        $next_page_options['attributes']['custom-element'] = 'amp-next-page';
        $libraries["amp.next-page"]["js"]["https://cdn.ampproject.org/v0/amp-next-page-1.0.js"] = $next_page_options;
        $libraries["amp.next-page"]['version'] = 1.0;
        unset($libraries["amp.next-page"]["js"]["https://cdn.ampproject.org/v0/amp-next-page-0.1.js"]);
      }
    }
  }

  /**
   * @param array<string, mixed> $variables
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if ($this->themeManager->getActiveTheme()->getName() !== 'liberal_theme_amp') {
      return;
    }

    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface || $node->getType() !== 'article_liberal') {
      return;
    }

    $is_advertising_article = liberalCustomContentUtils::checkIfNodeIsAdvertising($node);

    $list_url = Url::fromRoute('liberal_amp_loader.ampjson', ['nid' => $node->id()], ['absolute' => TRUE]);
    $list_absolute_url = $list_url->toString();

    $config = $this->configFactory->get('liberal_amp_loader.settings');
    $ga4_vars = [
      'vars' => [
        "gtag_id" => $config->get('ga4_measurement_id'),
        "config" => [
          $config->get('ga4_measurement_id') => ["groups" => "default"],
        ],
      ],
    ];

    $ga4_vars_json = $this->getAmpJson->encodeAmpJson($ga4_vars);

    $variables['page_top']['liberal_amp_analytics'] = [
      '#type' => 'html_tag',
      '#tag' => 'amp-analytics',
      '#attributes' => [
        'type' => 'gtag',
        'data-credentials' => 'include',
      ],
      'child' => [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => $ga4_vars_json,
        '#attributes' => [
          'type' => 'application/json',
        ],
      ],
    ];

    $read_more = $this->ampReadMoreBuilder->build($node);

    if ($read_more['markup'] !== '') {
      $variables['page']['read_more_articles'] = [
        '#markup' => $read_more['markup'],
      ];
    }

    // Impressions are queued (ad_tracker_queue); clicks write synchronously.
    if (!empty($read_more['promoted_nids'])) {
      $impressions_url = Url::fromRoute('unicorn_advertising_tools.statistics.amp.impressions', [], ['absolute' => TRUE])->toString();
      $clicks_url = Url::fromRoute('unicorn_advertising_tools.statistics.amp.clicks', [], ['absolute' => TRUE])->toString();

      $nids_query = implode('&', array_map(
        static fn (int $nid): string => 'nids[]=' . $nid,
        $read_more['promoted_nids'],
      ));

      $impression_config = [
        'requests' => [
          'impression' => $impressions_url . '?' . $nids_query,
        ],
        'triggers' => [
          'trackPromotedImpression' => [
            'on' => 'visible',
            'request' => 'impression',
          ],
        ],
        'transport' => [
          'xhrpost' => TRUE,
        ],
      ];

      // beacon, not xhrpost: survives the immediate page-navigation on click.
      $click_config = [
        'requests' => [
          'click' => $clicks_url . '?nid=${nid}',
        ],
        'triggers' => [
          'trackPromotedClick' => [
            'on' => 'click',
            'selector' => '.prm-article',
            'request' => 'click',
          ],
        ],
        'transport' => [
          'beacon' => TRUE,
        ],
      ];

      $variables['page_top']['unicorn_amp_promoted_impression'] = [
        '#type' => 'html_tag',
        '#tag' => 'amp-analytics',
        'child' => [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $this->getAmpJson->encodeAmpJson($impression_config),
          '#attributes' => [
            'type' => 'application/json',
          ],
        ],
      ];

      $variables['page_top']['unicorn_amp_promoted_click'] = [
        '#type' => 'html_tag',
        '#tag' => 'amp-analytics',
        'child' => [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $this->getAmpJson->encodeAmpJson($click_config),
          '#attributes' => [
            'type' => 'application/json',
          ],
        ],
      ];
    }

    // disable node loader behavior if the article is advertising
    if (!$is_advertising_article) {
      $variables['page_bottom']['liberal_amp_next_page'] = [
        '#type' => 'html_tag',
        '#tag' => 'amp-next-page',
        '#attributes' => [
          'src' => $list_absolute_url,
          'deep-parsing' => "true",
        ],
      ];
    }
  }

  /**
   * @param array<string, mixed> $page
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$page): void {
    // only on AMP pages remove the google tag manager scripts
    if ($this->ampContext->isAmpRoute()) {
      foreach ($page['#attached']['html_head'] as $key => $head) {
        if (str_contains((string) $head[1], 'big_pipe_detect_nojs')) {
          unset($page['#attached']['html_head'][$key]);
          break;
        }
      }
    }
  }

}
