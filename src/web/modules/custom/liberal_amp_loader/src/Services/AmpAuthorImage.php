<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Builds the author profile image render key for AMP article views.
 *
 * Port of the author-image block from the deleted liberal_ads module's
 * liberal_ads_node_view_alter().
 */
final readonly class AmpAuthorImage {

  private const string IMAGE_STYLE = 'liberal_author_image';

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private ImageFactory $imageFactory,
  ) {
  }

  /**
   * @return array<string, mixed>
   */
  public function build(NodeInterface $node): array {
    $authorIds = array_column($node->get('field_arthrografos')->getValue(), 'target_id');

    if ($authorIds === []) {
      return [];
    }

    $author = $this->entityTypeManager->getStorage('taxonomy_term')->load(reset($authorIds));

    if (!$author instanceof TermInterface) {
      return [];
    }

    $picIds = array_column($author->get('field_author_profile')->getValue(), 'target_id');

    if ($picIds === []) {
      return [];
    }

    $picture = $this->entityTypeManager->getStorage('file')->load(reset($picIds));

    if (!$picture instanceof FileInterface) {
      return [];
    }

    $uri = $picture->getFileUri();
    $authorName = $author->getName();
    $dimensions = ['width' => NULL, 'height' => NULL];

    $style = $this->entityTypeManager->getStorage('image_style')->load(self::IMAGE_STYLE);

    if ($uri !== NULL && $style instanceof ImageStyleInterface) {
      $image = $this->imageFactory->get($uri);
      $dimensions = ['width' => $image->getWidth(), 'height' => $image->getHeight()];
      $style->transformDimensions($dimensions, $uri);
    }

    return [
      'liberal_author_image' => [
        '#theme' => 'image_style',
        '#style_name' => self::IMAGE_STYLE,
        '#uri' => $uri,
        '#attributes' => [
          'alt' => $authorName,
          'title' => $authorName,
          'width' => $dimensions['width'],
          'height' => $dimensions['height'],
          'layout' => 'fixed',
          'class' => ['article__author-img'],
        ],
        '#cache' => ['max-age' => -1],
      ],
    ];
  }

}
