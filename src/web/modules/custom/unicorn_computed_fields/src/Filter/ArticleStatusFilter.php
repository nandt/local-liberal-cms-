<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\Filter;

use Drupal\unicorn_computed_fields\Enum\ArticleStatus;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Translates the public Article status filter to stored-field conditions.
 */
final readonly class ArticleStatusFilter {

  /**
   * @param array<string, mixed> $filters
   *
   * @return array<string, mixed>
   */
  public function translate(array $filters): array {
    if (!array_key_exists('article_status', $filters)) {
      return $filters;
    }

    $value = $filters['article_status'];
    if (!is_string($value) || preg_match('/^[0-2]$/', $value) !== 1) {
      throw new BadRequestHttpException('The article_status filter must be one of: 0, 1, 2.');
    }

    $status = ArticleStatus::from((int) $value);
    unset($filters['article_status']);

    $filters['article_status_native_status'] = [
      'condition' => [
        'path' => 'status',
        'operator' => '=',
        'value' => $status->isPublished() ? 1 : 0,
      ],
    ];

    if ($status->isPublished()) {
      return $filters;
    }

    $filters['article_status_native_publish_on'] = [
      'condition' => [
        'path' => 'publish_on',
        'operator' => $status->isDraft() ? 'IS NULL' : 'IS NOT NULL',
      ],
    ];

    return $filters;
  }

}
