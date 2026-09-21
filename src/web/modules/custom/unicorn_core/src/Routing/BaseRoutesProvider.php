<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Routing;

use Symfony\Component\Routing\RouteCollection;

abstract class BaseRoutesProvider
{
    abstract protected function register(RouteCollection $routes): RouteCollection;

    final public function __invoke(): RouteCollection
    {
        $routes = new RouteCollection();
        return $this->register($routes);
    }

  /**
   * @param array{class-string, non-empty-string} $callable
   */
    protected function controller(array $callable): string
    {
      [$class, $method] = $callable;

      return "{$class}::{$method}";
    }
}
