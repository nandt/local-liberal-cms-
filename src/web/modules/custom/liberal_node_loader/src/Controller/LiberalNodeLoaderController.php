<?php

namespace Drupal\liberal_node_loader\Controller;

use Drupal\Core\Cache\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\liberal_advertising_tools\Utils\LiberalAdvertisingToolsUtils;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @param Integer $nid
 * @param Request $request
 * @return JsonResponse
*/

class LiberalNodeLoaderController extends ControllerBase {

  use StringTranslationTrait;

  const DEFAULT_NODE_LOADER_TTL = 180;

  protected function jsRenderAjaxPage($scroll_node, $page_data, $active_theme, $cacheId) {
    $viewMode = 'full';
    $page_render = [];
    $read_more_render = [];
    $read_more_exists = FALSE;
    $active_theme_path = \Drupal::service('extension.list.theme')->getPath($active_theme);
    $twig_service = \Drupal::service('twig');

    if ($scroll_node instanceof NodeInterface) {
      $fields = $this->getFields($scroll_node);
      $node_build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($scroll_node, $viewMode);

      // Find the key of the value full
      $full_key = array_search('full', $node_build['#cache']['keys']);

      // Replace full with our custom key
      if ($full_key !== FALSE) {
        $node_build['#cache']['keys'][$full_key] = 'node_loader';
      }

      // inject ajaxloaded var before rendering
      $node_build['liberal']['ajaxLoaded'] = TRUE;

      // check if article_liberal markets
      if (isset($page_data->markets)) {
        $isLiberalMarketsArticle = TRUE;
      }
      else {
        $isLiberalMarketsArticle = FALSE;
      }

      // check for no ads during loader
      $field_no_advertisements = $scroll_node->get('field_no_advertisements')->getValue();
      $field_no_advertisements_value = 0;

      if (isset($field_no_advertisements[0]['value'])) {
        $field_no_advertisements_value = $field_no_advertisements[0]['value'];
      }

      if ($active_theme == 'liberal_stocks_mobile') {
        $content_array = 'liberal_stocks_mobile_node_content';
      }
      else {
        $content_array = 'node_content';
      }

      $count = count($scroll_node->get('field_homepage_bullets')->getValue());
      $read_more_html = [
        'html' => '',
        'cache_tags' => [],
      ];

      // one or more
      if ($count >= 1) {
        // Get the config factory service.
        $config_factory = \Drupal::service('config.factory');
        $block_1_id = 'liberal_theme_liberaladvertisingtoolsreadmore';

        $block_config = $config_factory->get('block.block.' . $block_1_id);
        $range_1 = $block_config->get('settings');

        $first_html = \Drupal::service('liberal_advertising_tools.read_more_articles')->getArticlesHTML($scroll_node, $range_1['distribute_read_more_articles'] ?? "1");
        $read_more_html['cache_tags'] = Cache::mergeTags($scroll_node->getCacheTags(), $first_html['cache_tags']);
        $read_more_html['html'] .= $first_html['html'] . '<div id="rpl-promoted-article"></div>';
        $read_more_exists = TRUE;

        // if there's more than one
        if ($count > 1) {
          $block_2_id = 'liberal_theme_liberaladvertisingtoolsreadmore_2';

          $block_config = $config_factory->get('block.block.' . $block_2_id);
          $range_2 = $block_config->get('settings');

          $second_html = \Drupal::service('liberal_advertising_tools.read_more_articles')->getArticlesHTML($scroll_node, $range_2['distribute_read_more_articles'] ?? "2-4");
          $read_more_html['html'] .= $second_html['html'];
          $read_more_html['cache_tags'] = Cache::mergeTags($read_more_html['cache_tags'], $second_html['cache_tags']);

          // add a possibility for a 2nd advertising article at more than 4 read more articles
          if ($count >= 4) {
            $read_more_html['html'] .= '<div id="rpl-promoted-article-2"></div>';
          }

          if ($count > 4) {
            $block_3_id = 'liberal_theme_liberaladvertisingtoolsreadmore_3';

            $block_config = $config_factory->get('block.block.' . $block_3_id);
            $range_3 = $block_config->get('settings');

            $third_html = \Drupal::service('liberal_advertising_tools.read_more_articles')->getArticlesHTML($scroll_node, $range_3['distribute_read_more_articles'] ?? "5-");
            $read_more_html['html'] .= $third_html['html'];
            $read_more_html['cache_tags'] = Cache::mergeTags($read_more_html['cache_tags'], $third_html['cache_tags']);
          }
        }
      }
      else {
        $read_more_html['html'] = '<div id="rpl-promoted-article"></div>';
      }

      // render the promoted / read more articles with the correct twig before sending to page twig
      if ($read_more_exists) {
        $template_file_read_more = $active_theme_path . '/templates/regions/region--read-more-articles.html.twig';
        $read_more_render = $twig_service->render($template_file_read_more, [
          'content' => ['#markup' => $read_more_html['html']],
          'is_node_loader' => TRUE,
        ]);
      }
      else {
        $read_more_render = $read_more_html['html'];
      }

      $render_array = [
        'isLiberalMarketsArticle' => $isLiberalMarketsArticle,
        'nodeTitle' => $scroll_node->getTitle(),
        'liberal' => [
          'ajaxLoaded' => TRUE,
          'loadNid' => $scroll_node->id(),
          'no_advertisements' => $field_no_advertisements_value,
        ],
        'page' => [
          'article_contents' => [
            $content_array => $node_build,
          ],
          'read_more_articles' => [
            '#markup' => $read_more_render,
          ],
        ],
      ];

      /* Use the same code as hook_render_template() but don't actually implement it
       *  because we don't want to interfere with other renderings
       */

      // Use our own twig file and put all that needs to be rendered there
      $template_file = $active_theme_path . '/templates/layout/page--article-liberal.html.twig';
      $page_render = $twig_service->render($template_file, $render_array);

      // Create the cache tags array.
      if (isset($read_more_html['cache_tags'])) {
        $cache_tags = Cache::mergeTags($scroll_node->getCacheTags(), $read_more_html['cache_tags']);
      }
      else {
        $cache_tags = $scroll_node->getCacheTags();
      }

      // set the fields and the render for caching
      $page_cache = [
        'render' => $page_render,
        'fields' => $fields,
        'cache_tags' => $cache_tags,
      ];

      // set the cache
      if (\Drupal::currentUser()->isAnonymous()) {
        \Drupal::cache()
          ->set(
            $cacheId,
            $page_cache,
            Cache::PERMANENT,
            $cache_tags
          );
      }

      return $page_cache;
    }
  }

  /**
   * @return mixed[]
   */
  protected function getFields($scroll_node): array {
    $fields = [];

    if ($scroll_node instanceof NodeInterface) {

      $field_no_advertisements = $scroll_node->get('field_no_advertisements')->getValue();
      $field_no_advertisements_value = 0;

      if (isset($field_no_advertisements[0]['value'])) {
        $field_no_advertisements_value = $field_no_advertisements[0]['value'];
      }

      $field_homepage_bullets_count = count($scroll_node->get('field_homepage_bullets')->getValue());

      $targeting_data = \Drupal::service('liberal_advertising_tools.liberal_ad_targeting')->getLiberalAdTargeting($scroll_node);
      $targeting_data_json = json_encode($targeting_data, JSON_UNESCAPED_UNICODE);

      $fields = [
        'title' => $scroll_node->getTitle(),
        'field_no_advertisements' => $field_no_advertisements_value,
        'field_homepage_bullets_count' => $field_homepage_bullets_count,
        'ad_target_data' => $targeting_data_json,
      ];
    }

    return $fields;
  }

  public function jsLiberalNodeLoad($nid, Request $request): JsonResponse {
    $scroll_node = NULL;
    $promoted_article_nids = [];

    // get data from ajax
    $page_data = json_decode($request->getContent());

    // Get the active theme to differentiate the cache id.
    $active_theme = \Drupal::theme()->getActiveTheme()->getName();

    // build the cache id string
    $cacheId = 'libajaxload:' . $active_theme . ':' . $nid;

    // render cache
    if ($cache = \Drupal::cache()->get($cacheId)) {
      $response_data = $cache->data;
    }
    else {
      // call the normal function and do the whole render
      $scroll_node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $response_data = $this->jsRenderAjaxPage($scroll_node, $page_data, $active_theme, $cacheId);
    }

    $fields = $response_data['fields'];
    $cache_tags = $response_data['cache_tags'];
    $response_data = $response_data['render'];

    $promoted_article = \Drupal::service('liberal_advertising_tools.read_more_articles')->getPromotedArticleHTML($nid);

    // only show a promoted article if it's allowed checking the advertisements boolean
    if (!empty($promoted_article && empty((int) $fields['field_no_advertisements']))) {
      /**
       * Add the promoted list tag so if any change happens in the advertising tools tag we clear the cache.
       * For example if an advertising article is completetly removed, we need to clear the cache to remove it
       */

      $promoted_default_cache_tag = [LiberalAdvertisingToolsUtils::CACHE_TAG_PROMOTED_LIST];
      $cache_tags = Cache::mergeTags($cache_tags, $promoted_default_cache_tag);

      // if there are other read more articles do not render region wrapper twig, other render
      if ($fields['field_homepage_bullets_count'] > 0) {
        $promoted_article_html = $promoted_article['html'];

        // in case there are 4 or more read more connected to the article load a second advertising article
        if ($fields['field_homepage_bullets_count'] >= 4) {
          $promoted_article_b = \Drupal::service('liberal_advertising_tools.read_more_articles')->getPromotedArticleHTML($nid);
        }
      }
      else {
        $region_title = '<h2 class="article_read_more__title mb-3"><span>' . $this->t('Read More') . '</span></h2>';
        $promoted_article_html = str_replace('<span class="title-placeholder"></span>', $region_title, $promoted_article['html_wrapper']);
      }

      $response_data = str_replace('<div id="rpl-promoted-article"></div>', $promoted_article_html, $response_data);
      $promoted_article_nids['nids'][$promoted_article['nid']] = $promoted_article['nid'];

      // place the 2nd advertising article
      if (!empty($promoted_article_b['html'])) {
        $response_data = str_replace('<div id="rpl-promoted-article-2"></div>', $promoted_article_b['html'], $response_data);
        $promoted_article_nids['nids'][$promoted_article_b['nid']] = $promoted_article_b['nid'];
      }
    }

    $response_array = [
      'html' => $response_data,
      'nodeUrl' => Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString(TRUE)->getGeneratedUrl(),
      'nodeTitle' => $fields['title'] . ' | Liberal.gr',
      'loadedNid' => $nid,
      'readMoreNids' => $promoted_article_nids ?? NULL,
      'nodeAds' => (int) $fields['field_no_advertisements'],
      'targetData' => $fields['ad_target_data'] ?? '{}',
    ];

    $response = new JsonResponse($response_array);

    // get config for TTL
    $config = \Drupal::config('liberal_async_blocks.admin_settings');
    $edge_cache_ttl = (int) $config->get('node_loader_ttl')['node_loader_articles_ttl'] ?? self::DEFAULT_NODE_LOADER_TTL;

    // Set cache headers
    $response->setSharedMaxAge($edge_cache_ttl);
    $response->setMaxAge($edge_cache_ttl);
    $response->setPublic();

    // Add cache tags
    $response->headers->set('Cache-Tags', implode(' ', $cache_tags));

    return $response;
  }

}
