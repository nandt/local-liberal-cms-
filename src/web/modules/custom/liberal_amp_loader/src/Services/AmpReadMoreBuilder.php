<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\node\NodeInterface;
use Drupal\unicorn_advertising_tools\Configuration\AdvertisingToolsConfig;
use Drupal\unicorn_advertising_tools\Support\AdvertisingToolsUtils;
use Drupal\unicorn_core\Support\Collection;

/**
 * Builds the AMP "read more" region server-side, splicing in promoted
 * articles via unicorn_advertising_tools's selection logic.
 */
final readonly class AmpReadMoreBuilder {

  private const string VIEW_MODE = 'read_more';

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private RendererInterface $renderer,
    private TwigEnvironment $twig,
    private ThemeExtensionList $themeExtensionList,
    private AdvertisingToolsUtils $advertisingToolsUtils,
    private AdvertisingToolsConfig $advertisingToolsConfig,
  ) {
  }

  /**
   * @return array{markup: string, promoted_nids: list<int>}
   */
  public function build(NodeInterface $node): array {
    $bullets = Collection::wrap($node->get('field_homepage_bullets')->getValue())->pluck('target_id');
    $promotedNids = Collection::wrap([]);

    // selectNodes() throws on an empty pool, so check first.
    $promotedPool = $this->advertisingToolsUtils->fetchPromotedArticles()
      ->reject(fn ($row, $entityId): bool => (int) $entityId === (int) $node->id());

    if ($promotedPool->isNotEmpty()) {
      $promotedNids = $this->advertisingToolsUtils->selectNodes($bullets, (int) $node->id());
    }

    $this->spliceIntoList($bullets, $promotedNids);

    if ($bullets->isEmpty()) {
      return ['markup' => '', 'promoted_nids' => []];
    }

    $promotedNidsList = array_values($promotedNids->toArray());
    $itemsMarkup = $this->renderItems($bullets, $promotedNidsList);

    if ($itemsMarkup === '') {
      return ['markup' => '', 'promoted_nids' => []];
    }

    $templatePath = $this->themeExtensionList->getPath('liberal_theme_amp') . '/templates/regions/region--read-more-articles.html.twig';

    $markup = $this->twig->render($templatePath, [
      'content' => Markup::create($itemsMarkup),
      'rm_print_title' => TRUE,
      'is_node_loader' => FALSE,
    ]);

    return ['markup' => $markup, 'promoted_nids' => $promotedNidsList];
  }

  /**
   * @param Collection<int, int|string> $bullets
   * @param list<int> $promotedNids
   */
  private function renderItems(Collection $bullets, array $promotedNids): string {
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($bullets->toArray());
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');

    $markup = '';

    foreach ($bullets->toArray() as $nid) {
      $readMoreNode = $nodes[$nid] ?? NULL;

      if (!$readMoreNode instanceof NodeInterface
        || $readMoreNode->bundle() !== 'article_liberal'
        || !$readMoreNode->isPublished()) {
        continue;
      }

      $isPromoted = in_array((int) $nid, $promotedNids, TRUE);

      $build = $viewBuilder->view($readMoreNode, self::VIEW_MODE);
      $build['#unicorn_promoted'] = $isPromoted;

      // Vary cache by theme + promoted flag, or AMP can serve a stale render.
      if (isset($build['#cache'])) {
        $build['#cache']['contexts'][] = 'theme';
        if (isset($build['#cache']['keys'])) {
          $build['#cache']['keys'][] = 'unicorn_promoted:' . ($isPromoted ? '1' : '0');
        }
      }

      // render(), not renderRoot(): already inside hook_preprocess_html()'s render context.
      $markup .= $this->renderer->render($build);
    }

    return $markup;
  }

  /**
   * Local port of ReadMoreArticles::insertPromotedArticles() (headless FE).
   *
   * @param Collection<int, int|string> $bullets
   * @param Collection<int, int> $promotedNids
   */
  private function spliceIntoList(Collection $bullets, Collection $promotedNids): void {
    if ($promotedNids->isEmpty()) {
      return;
    }

    if ($bullets->isEmpty()) {
      $first = $promotedNids->first();

      if ($first !== NULL) {
        $bullets->add($first);
      }

      return;
    }

    $remaining = Collection::wrap($promotedNids->toArray());

    $this->advertisingToolsConfig->getPromotedArticlePositions()->each(function ($position) use ($bullets, $remaining): void {
      if ($remaining->isNotEmpty()) {
        $promoted = $remaining->first();
        $bullets->splice($position, 0, $promoted);
        $remaining->removeElement($promoted);
      }
    });
  }

}
