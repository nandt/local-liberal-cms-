<?php

declare(strict_types=1);

namespace Drupal\unicorn_core;

use Drupal\unicorn_core\Support\Http\Request as RequestAlias;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class RequestArgumentResolver implements ValueResolverInterface
{
  public function resolve(Request $request, ArgumentMetadata $argument): iterable
  {
    $type = $argument->getType();
    $isOurRequest = is_string($type) && is_a($type, RequestAlias::class, true);

    return $isOurRequest ? [$this->buildRequestObj($argument, $request)] : [];
  }

  public function buildRequestObj(ArgumentMetadata $argument, Request $request): RequestAlias
  {
    /** @var class-string<RequestAlias> $requestClass */
    $requestClass = $argument->getType();

    return $requestClass::createFromRequest($request);
  }
}
