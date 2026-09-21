<?php

declare(strict_types=1);

namespace Drupal\liberal_amp_loader\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\liberal_amp_loader\Services\GetAmpJson;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class LiberalAmpLoaderGetJson extends ControllerBase {

  public function __construct(
    private readonly GetAmpJson $getAmpJson,
  ) {
  }

  #[\Override]
  public static function create(ContainerInterface $container): static {
    $getAmpJson = $container->get(GetAmpJson::class);
    assert($getAmpJson instanceof GetAmpJson);

    return new static($getAmpJson);
  }

  public function getAmpNextPageList($nid, Request $request): JsonResponse {
    // getRenderedAmpJson() already returns an encoded JSON string, hence json: true.
    $response = $this->getAmpJson->getRenderedAmpJson($nid);

    return ResponseBuilder::make(
      $response,
      200,
      ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'],
      TRUE,
    )->json();
  }

}
