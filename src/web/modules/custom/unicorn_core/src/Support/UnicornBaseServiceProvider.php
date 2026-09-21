<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Support;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class UnicornBaseServiceProvider extends ServiceProviderBase
{

  public function register(ContainerBuilder $container): void
  {
    $this->registerPublicClasses($container);
  }

  /**
   * @return list<class-string>
   */
  protected function getPublicClasses(): array
  {
    return [];
  }

  private function registerPublicClasses(ContainerBuilder $container): void
  {
    Collection::wrap($this->getPublicClasses())
      ->each(function (string $class) use ($container): void
      {
        $container->register($class)->setAutowired(true)->setAutoconfigured(true);
      });
  }

}
