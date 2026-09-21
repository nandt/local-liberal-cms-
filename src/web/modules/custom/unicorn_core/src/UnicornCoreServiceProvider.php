<?php

declare(strict_types=1);

namespace Drupal\unicorn_core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\unicorn_core\EventSubscriber\ValidationExceptionSubscriber;
use Drupal\unicorn_core\Resources\PaginatorResource;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_core\Support\UnicornBaseServiceProvider;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Symfony\Component\DependencyInjection\Reference;

class UnicornCoreServiceProvider extends UnicornBaseServiceProvider
{
  #[\Override]
  public function register(ContainerBuilder $container): void
  {
    parent::register($container);

    $container->register('unicorn_core.request_argument_resolver', RequestArgumentResolver::class)
      ->setPublic(false);

    $container->register(ExtendedValidator::class)
      ->setAutowired(true)
      ->setAutoconfigured(true);

    $container->register(PaginatorResource::class)
      ->setAutowired(true)
      ->setAutoconfigured(true);

    $container->register(ValidationExceptionSubscriber::class)
      ->setAutowired(true)
      ->setAutoconfigured(true);
  }

  public function alter(ContainerBuilder $container): void
  {
    $this->replaceRequest($container);
  }

  protected function replaceRequest(ContainerBuilder $container): void {
    // careful here, we assume the 2nd argument in the resolvers.
    $definition = $container->getDefinition('http_kernel.controller.argument_resolver');
    $resolvers = $definition->getArgument(1);

    if (!is_array($resolvers)) {
      return;
    }

    $definition->setArgument(1, [
      new Reference('unicorn_core.request_argument_resolver'),
      ...$resolvers,
    ]);
  }
}
