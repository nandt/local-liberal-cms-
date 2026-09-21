<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader;

use Drupal\liberal_amp_loader\Services\AmpAdTargeting;
use Drupal\liberal_amp_loader\Services\AmpAuthorImage;
use Drupal\liberal_amp_loader\Services\AmpImpressionPixel;
use Drupal\liberal_amp_loader\Services\AmpNextPageNids;
use Drupal\liberal_amp_loader\Services\AmpReadMoreBuilder;
use Drupal\liberal_amp_loader\Services\AmpVideoEmbed;
use Drupal\liberal_amp_loader\Services\GetAmpJson;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;

class LiberalAmpLoaderServiceProvider extends UnicornBaseServiceProvider {

  #[\Override]
  protected function getPublicClasses(): array {
    return [
      GetAmpJson::class,
      AmpAdTargeting::class,
      AmpAuthorImage::class,
      AmpReadMoreBuilder::class,
      AmpVideoEmbed::class,
      AmpImpressionPixel::class,
      AmpNextPageNids::class,
    ];
  }

}
