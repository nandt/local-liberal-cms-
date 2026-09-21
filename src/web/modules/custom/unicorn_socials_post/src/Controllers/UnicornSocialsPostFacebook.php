<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_socials_post\Configuration\FacebookConfig;
use Drupal\unicorn_socials_post\Resources\SocialsPostResource;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use Drupal\unicorn_socials_post\Services\SocialTasks;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @phpstan-consistent-constructor
 */
class UnicornSocialsPostFacebook extends ControllerBase {

  private readonly LoggerInterface $logger;

  private readonly EntityStorageInterface $nodeStorage;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly SocialTasks $socialTasks,
    private readonly FacebookConfig $config,
    private readonly SocialsPostResource $socialsPostResource,
    LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
    $this->logger = $loggerChannelFactory->get('socials_post_facebook');
    $this->nodeStorage = $this->entityTypeManager()->getStorage('node');
  }

  public function post(int $nid): JsonResponse {

    $this->config->throwIfHasMissingValue();

    $node = $this->nodeStorage->load($nid);
    if(!$node instanceof NodeInterface) {
      throw new BadRequestHttpException('Something went wrong.');
    }

    $this->facebookShare($node);
    $values = $this->socialTasks->updateCounters($node, 'facebook');

    $data = $values ? $this->socialsPostResource->toDashboardApiSingle($values, 'facebook') : [];
    return ResponseBuilder::make($data)->json();
  }


  private function facebookShare(NodeInterface $node): void {

    $absoluteUrl = $this->socialTasks->getSocialShareUrl($node);
    $socialsMessage = $this->socialTasks->getSocialsMessage($node);

      $this->sendRequest(
        [
          'link' => $absoluteUrl,
          'message' => $socialsMessage,
          'access_token' => $this->config->getAccessToken()
        ]
      );
  }

  /**
   * @param array<string,string> $params
   * @throws Throwable
   */
  private function sendRequest(array $params): ResponseInterface {
      try {
        $response = $this->httpClient->request(
          "POST",
          "https://graph.facebook.com/{$this->config->getPageId()}/feed",
          [ 'form_params' => $params ]
        );
      }
      catch (Throwable $e) {
        $this->logger->error($e->getMessage());
        throw $e;
      }

    if($response->getStatusCode() !== 200) {
      $this->logger->error('Something went wrong with page request for page ID: @page_id', ['@page_id' => $this->config->getPageId()]);

      throw new HttpException($response->getStatusCode(), $response->getReasonPhrase());
    }

    return $response;
  }
}
