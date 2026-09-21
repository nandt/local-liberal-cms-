<?php

declare(strict_types=1);

namespace Drupal\unicorn_computed_fields\EventSubscriber;

use Drupal\unicorn_computed_fields\Filter\ArticleStatusFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the public Article status filter to the JSON:API collection.
 */
final readonly class ArticleStatusFilterSubscriber implements EventSubscriberInterface {

  private const string ARTICLE_COLLECTION_ROUTE = 'jsonapi.node--article_liberal.collection';

  public function __construct(
    private ArticleStatusFilter $articleStatusFilter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    if ($request->getMethod() !== Request::METHOD_GET
      || $request->attributes->get('_route') !== self::ARTICLE_COLLECTION_ROUTE) {
      return;
    }

    $filters = $request->query->all('filter');
    if (!array_key_exists('article_status', $filters)) {
      return;
    }

    $request->query->set('filter', $this->articleStatusFilter->translate($filters));
  }

}
