<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article;

use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_video_article\Configuration\VideoArticleConfig;
use Drupal\unicorn_video_article\Plugin\Validation\Constraint\UniqueEpisodeConstraintValidator;
use Drupal\unicorn_video_article\Plugin\Validation\Constraint\ValidAttachedVideoCodeConstraintValidator;
use Drupal\unicorn_video_article\Plugin\Validation\Constraint\ValidVideoSourceCodeConstraintValidator;
use Drupal\unicorn_video_article\Service\GetVideoArticleData;
use Drupal\unicorn_video_article\Support\HtmlFragmentValidator;
use Drupal\unicorn_video_article\Support\VideoArticleSupport;

class UnicornVideoArticleServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    // @phpstan-ignore-next-line
    return [
      GetVideoArticleData::class,
      HtmlFragmentValidator::class,
      VideoArticleConfig::class,
      VideoArticleSupport::class,
      UniqueEpisodeConstraintValidator::class,
      ValidAttachedVideoCodeConstraintValidator::class,
      ValidVideoSourceCodeConstraintValidator::class,
    ];
  }

}
