<?php

declare(strict_types=1);

namespace Drupal\unicorn_field_mapper\Configuration;

class FieldMapConfiguration {

  /**
   * @var array<string, array{
   *   subtitle: string,
   *   body: string,
   *   mainImage: string
   * }>
   */
  private static array $config = [
    'article_liberal' => [
      'subtitle' => 'field_subtitle',
      'body' => 'body',
      'mainImage' => 'field_kentriki_fotografia',
    ]
  ];

  /**
   * @return array<string, array{
   *   subtitle: string,
   *   body: string,
   *   mainImage: string
   * }>
   */
  public function getFieldConfiguration(): array {
    return self::$config;
  }
}
