<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\unicorn_video_article\Service\GetVideoArticleData;
use Drupal\unicorn_video_article\Support\VideoArticleSupport;

readonly class VideoArticleHooks {

  public function __construct(
    private VideoArticleSupport $videoArticleSupport,
    private GetVideoArticleData $getVideoArticleData,
  ) {}

  /**
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($bundle !== 'article_liberal' || $entity_type->id() !== 'node') {
      return;
    }

    if (isset($fields['field_vid_article_episode'])) {
      $fields['field_vid_article_episode']->addConstraint('UniqueEpisode');
    }

    if (isset($fields['field_vid_article_source_code'])) {
      $fields['field_vid_article_source_code']->addConstraint('ValidVideoSourceCode');
    }

    if (isset($fields['field_attached_video_code'])) {
      $fields['field_attached_video_code']->addConstraint('ValidAttachedVideoCode');
    }
  }

  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if (!$this->videoArticleSupport->isVideoArticle($node)) {
      return;
    }

    if (!$this->sourceCodeChanged($node)) {
      return;
    }

    $sourceCode = $node->get('field_vid_article_source_code')->value ?? '';
    $videoId = $this->videoArticleSupport->extractVideoId($sourceCode);

    if (!$videoId) {
      return;
    }

    $videoDto = $this->getVideoArticleData->get($videoId);

    if (!$videoDto) {
      return;
    }

    $node->set('field_vid_article_duration', $videoDto->duration);
    $node->set('field_vid_article_width', $videoDto->width);
    $node->set('field_vid_article_height', $videoDto->height);
  }

  private function sourceCodeChanged(NodeInterface $node): bool {
    if ($node->isNew()) {
      return TRUE;
    }

    $original = $node->getOriginal();

    return $original instanceof NodeInterface
      && $node->get('field_vid_article_source_code')->value
        !== $original->get('field_vid_article_source_code')->value;
  }

}
