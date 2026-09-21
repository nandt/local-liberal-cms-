<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Configuration;

class SearchConfig {
  private const string INDEX_ID = 'liberal_index';
  private const string SEARCH_IMAGE_STYLE = 'liberal_article_image';

  private const string SEARCH_API_IMAGE_FIELD = 'field_kentriki_fotografia';
  private const string SEARCH_API_IMAGE_STYLE_URL_PROPERTY = 'image_style_url';
  private const string SEARCH_API_IMAGE_STYLE_URL_FIELD = 'image_url';
  private const string SEARCH_API_URL_FIELD = 'url';

  public function getIndexId(): string {
    return self::INDEX_ID;
  }

  public function getSearchImageStyle(): string {
    return self::SEARCH_IMAGE_STYLE;
  }

  public function getSearchApiImageField(): string {
    return self::SEARCH_API_IMAGE_FIELD;
  }

  public function getSearchApiImageStyleUrlProperty(): string {
    return self::SEARCH_API_IMAGE_STYLE_URL_PROPERTY;
  }

  public function getSearchApiImageStyleUrlField(): string {
    return self::SEARCH_API_IMAGE_STYLE_URL_FIELD;
  }

  public function getSearchApiUrlField(): string {
    return self::SEARCH_API_URL_FIELD;
  }

}
