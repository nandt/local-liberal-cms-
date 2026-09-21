<?php

namespace Drupal\liberal_sitemap_generator\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\liberal_sitemap_generator\Controller\LiberalSitemapGeneratorController;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Attributes as CLI;

class SitemapGeneratorCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Generate Sitemaps
   */
  #[CLI\Command(name: 'sitemap:generate', aliases: ['smap-gen'])]
  #[CLI\Argument(name: 'variant', description: 'What kind of sitemap to build.')]
  #[CLI\Option(name: 'date_option', description: 'Possible values: day. today, month, month-current, month-previous, all.')]
  #[CLI\Option(name: 'date', description: 'Set the date here if you want to delete based on a set date, ex 20-03-2023 (d-m-Y). The time used is the one the command runs')]
  #[CLI\Option(name: 'exclude_current_month', description: 'Only relevant when executing all, month-to-now.')]
  #[CLI\Usage(name: 'drush sitemap:generate --date_option="month" --date="01-03-2023" articles', description: 'Variants: news, articles, index, stocks, indices, index_xrimatistirio, podcasts. The date is always a full day in d-m-Y, even for month options.')]

  public function generate($variant, $options = ['date' => NULL, 'date_option' => NULL, 'exclude_current_month' => NULL]) {
    $this->logger()->notice($this->t('Sitemap @variant generation started...', ['@variant' => $variant]));
    $generate = new LiberalSitemapGeneratorController();
    $generate->setExecuteBatch($variant, $options, 'drush');
  }

}
