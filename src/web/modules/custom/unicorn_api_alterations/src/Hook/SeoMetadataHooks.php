<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

final readonly class SeoMetadataHooks {

  private const string ARTICLE_BUNDLE = 'article_liberal';
  private const string BYLINE_FIELD = 'field_arthrografos';
  private const string NEWSROOM_FALLBACK = 'LiberalNewsRoom';

  private const string ARTICLE_TYPE_FIELD = 'field_eidos_arthrou';
  private const string VIDEO_TERM_NAME = 'Βίντεο';
  private const string DURATION_FIELD = 'field_vid_article_duration';
  private const string EMBED_FIELD = 'field_vid_article_source_code';
  private const string SUBTITLE_FIELD = 'field_subtitle';
  private const string CATEGORY_FIELD = 'field_liberal_category';

  /**
   * @param array<string, mixed> $metatags
   * @param array<string, mixed> $context
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$metatags, array $context): void {
    $entity = $context['entity'] ?? NULL;

    if (!$entity instanceof NodeInterface || $entity->bundle() !== self::ARTICLE_BUNDLE) {
      return;
    }

    $this->applyNewsroomFallback($metatags, $entity);

    if ($this->isVideoArticle($entity)) {
      $this->applyVideoObject($metatags, $entity);
    }
  }

  /**
   * @param array<string, mixed> $metatags
   */
  private function applyNewsroomFallback(array &$metatags, NodeInterface $node): void {
    if (empty($metatags['schema_article_author'])) {
      return;
    }

    if ($this->hasValue($node, self::BYLINE_FIELD)) {
      return;
    }

    $author = @unserialize($metatags['schema_article_author'], ['allowed_classes' => FALSE]);

    if (!is_array($author)) {
      return;
    }

    $author['name'] = self::NEWSROOM_FALLBACK;
    unset($author['url']);

    $metatags['schema_article_author'] = serialize($author);
  }

  private function isVideoArticle(NodeInterface $node): bool {
    if (!$this->hasValue($node, self::ARTICLE_TYPE_FIELD)) {
      return FALSE;
    }

    foreach ($node->get(self::ARTICLE_TYPE_FIELD)->referencedEntities() as $term) {
      if ($term->label() === self::VIDEO_TERM_NAME) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @param array<string, mixed> $metatags
   */
  private function applyVideoObject(array &$metatags, NodeInterface $node): void {
    $metatags['schema_article_type'] = 'VideoObject';
    $metatags['schema_article_in_language'] = 'el';

    // Ιδιότητες του Article που δεν ανήκουν στο VideoObject. Το dateModified
    // φεύγει γιατί τα Video Articles δεν έχουν συντακτική «τελευταία ενημέρωση».
    unset(
      $metatags['schema_article_date_modified'],
      $metatags['schema_article_headline'],
      $metatags['schema_article_section'],
      $metatags['schema_article_body'],
    );

    $duration = $this->isoDuration($node);
    if ($duration !== NULL) {
      $metatags['schema_article_duration'] = $duration;
    }

    foreach ($this->videoUrls($node) as $key => $url) {
      $metatags['schema_article_' . $key] = $url;
    }

    $metatags['schema_article_about'] = serialize([
      '@type' => 'Thing',
      'name' => $this->aboutName($node),
    ]);

    $genre = $this->firstCategoryName($node);
    if ($genre !== NULL) {
      $metatags['schema_article_genre'] = $genre;
    }
  }

  private function isoDuration(NodeInterface $node): ?string {
    if (!$this->hasValue($node, self::DURATION_FIELD)) {
      return NULL;
    }

    $seconds = (int) round((float) $node->get(self::DURATION_FIELD)->value);

    if ($seconds <= 0) {
      return NULL;
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainder = $seconds % 60;

    return 'PT'
      . ($hours > 0 ? $hours . 'H' : '')
      . ($minutes > 0 ? $minutes . 'M' : '')
      . ($remainder > 0 ? $remainder . 'S' : '');
  }

  /**
   * @return array<string, string>
   */
  private function videoUrls(NodeInterface $node): array {
    if (!$this->hasValue($node, self::EMBED_FIELD)) {
      return [];
    }

    $markup = (string) $node->get(self::EMBED_FIELD)->value;

    if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $markup, $matches) !== 1) {
      return [];
    }

    $source = $matches[1];
    $urls = ['embed_url' => strtok($source, '?')];

    if (str_contains($source, 'cloudflarestream.com')
      && preg_match('#cloudflarestream\.com/([^/?]+)/#', $source, $id) === 1) {
      $urls['content_url'] = sprintf('https://videodelivery.net/%s/manifest/video.m3u8', $id[1]);
    }

    return $urls;
  }

  private function aboutName(NodeInterface $node): string {
    $title = (string) $node->getTitle();

    if (!$this->hasValue($node, self::SUBTITLE_FIELD)) {
      return $title;
    }

    $subtitle = trim(strip_tags((string) $node->get(self::SUBTITLE_FIELD)->value));

    return $subtitle === '' ? $title : $subtitle . ' : ' . $title;
  }

  private function firstCategoryName(NodeInterface $node): ?string {
    if (!$this->hasValue($node, self::CATEGORY_FIELD)) {
      return NULL;
    }

    $terms = $node->get(self::CATEGORY_FIELD)->referencedEntities();
    $first = reset($terms);

    return $first === FALSE ? NULL : $first->label();
  }

  private function hasValue(NodeInterface $node, string $field): bool {
    return $node->hasField($field) && !$node->get($field)->isEmpty();
  }

}
