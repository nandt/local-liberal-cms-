<?php

declare(strict_types=1);

use DrupalRector\Set\DrupalSetProvider;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

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
  // start conserative here, then up the levels
  ->withTypeCoverageLevel(10)
  ->withTypeCoverageDocblockLevel(10)
  ->withDeadCodeLevel(5)
  ->withCodeQualityLevel(10)
  ->withSkip([
    __DIR__ . '/web/modules/custom/*/vendor',
    __DIR__ . '/web/modules/custom/*/node_modules',
    __DIR__ . '/web/themes/custom/*/vendor',
    __DIR__ . '/web/themes/custom/*/node_modules',
    __DIR__ . '/web/themes/custom/*/dist',
    __DIR__ . '/web/themes/custom/*/build',
    // this is because Drupal wants to serialize in some places and this rule is NOT always safe
    ArrayToFirstClassCallableRector::class,
  ])
  ->withPhpSets(php84: true)
  ->withSetProviders(DrupalSetProvider::class)
  ->withComposerBased(
    twig: true,
    phpunit: true,
    symfony: true,
    drupal: true,
  )
  ->withParallel()
  ->withCache(
    __DIR__ . '/tmp/rector',
    FileCacheStorage::class
  );
