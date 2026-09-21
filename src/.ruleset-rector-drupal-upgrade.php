<?php

declare(strict_types=1);

use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal9SetList;
use DrupalRector\Set\Drupal8SetList;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/web/modules/custom',
    __DIR__ . '/web/themes/custom',
  ])
  ->withFileExtensions([
    'php',
    'module',
    'theme',
    'install',
    'profile',
  ])
  ->withSkip([
    __DIR__ . '/web/modules/custom/*/vendor',
    __DIR__ . '/web/modules/custom/*/node_modules',
    __DIR__ . '/web/themes/custom/*/vendor',
    __DIR__ . '/web/themes/custom/*/node_modules',
    __DIR__ . '/web/themes/custom/*/dist',
    __DIR__ . '/web/themes/custom/*/build',
  ])
  ->withSets([
    Drupal8SetList::DRUPAL_8,
    Drupal9SetList::DRUPAL_9,
    Drupal10SetList::DRUPAL_10,
  ])
  ->withParallel()
  ->withCache(
    __DIR__ . '/tmp/rector',
    FileCacheStorage::class
  );
