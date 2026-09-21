<?php

declare(strict_types=1);

use DrupalRector\Set\Drupal10SetList;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/custom_packages',
  ])
  ->withFileExtensions([
    'php',
    'module',
    'theme',
    'install',
    'profile',
  ])
  // start conserative here, then up the levels
  ->withTypeCoverageLevel(5)
  ->withTypeCoverageDocblockLevel(1)
  ->withDeadCodeLevel(1)
  ->withCodeQualityLevel(1)
  ->withSkip([
    __DIR__ . '/custom_packages/**/vendor/**',
    __DIR__ . '/custom_packages/**/node_modules/**',
    __DIR__ . '/custom_packages/**/dist/**',
    __DIR__ . '/custom_packages/**/build/**',
  ])
  ->withSets([
    Drupal10SetList::DRUPAL_10,
  ])
  ->withParallel()
  ->withCache(
    __DIR__ . '/tmp/rector',
    FileCacheStorage::class
  );
