<?php

use Drupal\Core\Installer\InstallerKernel;

if (!InstallerKernel::installationAttempted() && extension_loaded('redis') && getenv('REDIS_HOST')) {

  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('REDIS_HOST');
  $settings['redis.connection']['port'] = (int) (getenv('REDIS_PORT_INTERNAL') ?: 6379);

  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['redis_invalidate_all_as_delete'] = TRUE;

  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

  $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');

  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'redis.factory' => [
        'class' => 'Drupal\redis\ClientFactory',
      ],
      'cache.backend.redis' => [
        'class' => 'Drupal\redis\Cache\CacheBackendFactory',
        'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
      ],
      'cache.container' => [
        'class' => '\Drupal\redis\Cache\PhpRedis',
        'factory' => ['@cache.backend.redis', 'get'],
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
        'arguments' => ['@redis.factory'],
      ],
      'serialization.phpserialize' => [
        'class' => 'Drupal\Component\Serialization\PhpSerialize',
      ],
    ],
  ];

  // Queues to Redis
  $settings['queue_service_ad_tracker_queue'] = 'queue.redis';
  $settings['queue_service_unicorn_statistics_queue'] = 'queue.redis';
}
