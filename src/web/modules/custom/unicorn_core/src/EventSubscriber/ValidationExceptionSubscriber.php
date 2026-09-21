<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\EventSubscriber;

use Drupal\unicorn_core\Response\ResponseBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final readonly class ValidationExceptionSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::EXCEPTION => ['onException', 50],
    ];
  }

  public function onException(ExceptionEvent $event): void {

    $request = $event->getRequest();

    if (!$this->isJsonRequest($request)) {
      return;
    }

    $exception = $event->getThrowable();

    if (!$exception instanceof ValidationFailedException) {
      return;
    }

    $event->setResponse(
      ResponseBuilder::make($exception->getValue(), Response::HTTP_UNPROCESSABLE_ENTITY)->json()
    );
  }

  private function isJsonRequest(Request $request): bool
  {
    $type = 'application/json';

    $possibleHeaders = [
      $request->headers->get('Content-Type'),
      $request->headers->get('Accept'),
      $request->headers->get('X-Requested-With'),
    ];

    return in_array($type, $possibleHeaders, true);
  }

}
