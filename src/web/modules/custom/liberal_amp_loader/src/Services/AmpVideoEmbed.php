<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_video_article\Support\VideoArticleSupport;

/**
 * Builds the amp-iframe video embed for video articles.
 *
 * amp-iframe (Cloudflare's own player), not amp-video: Cloudflare Stream's
 * HLS manifest only plays natively in Safari via amp-video.
 */
final readonly class AmpVideoEmbed {

  private const string FEATURED_IMAGE_STYLE = 'liberal_article_image';

  // 4:3, matching liberal_article_image -- the slot this replaces.
  private const float MAX_HEIGHT_RATIO = 0.75;

  public function __construct(
    private VideoArticleSupport $videoArticleSupport,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  public function isApplicable(NodeInterface $node): bool {
    return $this->videoArticleSupport->isVideoArticle($node);
  }

  /**
   * @return array<string, mixed>|null
   */
  public function build(NodeInterface $node): ?array {
    $sourceCode = (string) ($node->get('field_vid_article_source_code')->value ?? '');
    $src = $this->extractIframeSrc($sourceCode);

    if ($src === NULL) {
      return NULL;
    }

    $width = (int) ($node->get('field_vid_article_width')->value ?? 0);
    $height = (int) ($node->get('field_vid_article_height')->value ?? 0);

    if ($width <= 0 || $height <= 0) {
      $width = 16;
      $height = 9;
    }

    // Cap the ratio, not the pixels: layout="responsive" only cares about
    // width:height, and a portrait video would otherwise stretch the page.
    if ($height / $width > self::MAX_HEIGHT_RATIO) {
      $height = (int) round($width * self::MAX_HEIGHT_RATIO);
    }

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'amp-iframe',
      '#attributes' => [
        'src' => $src,
        'width' => $width,
        'height' => $height,
        'layout' => 'responsive',
        'frameborder' => '0',
        'sandbox' => 'allow-scripts allow-same-origin',
        'allow' => 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;',
        'allowfullscreen' => TRUE,
      ],
    ];

    $placeholder = $this->buildPlaceholder($node);

    if ($placeholder !== NULL) {
      $build['placeholder'] = $placeholder;
    }

    return $build;
  }

  // Not shared with VideoArticleSupport::extractVideoId() (returns only the
  // ID); this needs the full src, so it's kept self-contained here.
  private function extractIframeSrc(string $sourceCode): ?string {
    if (!preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $sourceCode, $matches)) {
      return NULL;
    }

    return $matches[1];
  }

  /**
   * amp-iframe requires a placeholder above the 75% viewport fold.
   *
   * @return array<string, mixed>|null
   */
  private function buildPlaceholder(NodeInterface $node): ?array {
    $targetId = $node->get('field_kentriki_fotografia')->target_id ?? NULL;

    if (empty($targetId)) {
      return NULL;
    }

    $file = $this->entityTypeManager->getStorage('file')->load($targetId);

    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $style = $this->entityTypeManager->getStorage('image_style')->load(self::FEATURED_IMAGE_STYLE);
    $uri = $file->getFileUri();

    if (!$style instanceof ImageStyleInterface || $uri === NULL) {
      return NULL;
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'amp-img',
      '#attributes' => [
        'src' => $style->buildUrl($uri),
        'placeholder' => TRUE,
        'layout' => 'fill',
        'alt' => $node->getTitle(),
      ],
    ];
  }

}
