<?php

namespace Drupal\unicorn_socials_post\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Abraham\TwitterOAuth\TwitterOAuth;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Collection;
use Drupal\unicorn_socials_post\Configuration\XConfig;
use Drupal\unicorn_socials_post\Resources\SocialsPostResource;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\NodeInterface;
use Drupal\unicorn_socials_post\Services\SocialTasks;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @phpstan-consistent-constructor
 */
class UnicornSocialsPostX extends ControllerBase {

  private readonly LoggerInterface $logger;

  private readonly EntityStorageInterface $nodeStorage;

  public function __construct(
    private readonly SocialTasks $socialTasks,
    private readonly SocialsPostResource $socialsPostResource,
    private readonly XConfig $config,
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

    $result = $this->xShare($node);
    $values = $this->socialTasks->updateCounters($node, 'x');

    $data = [];
    if($values) {
      $data = $this->socialsPostResource->toDashboardApiSingle($values, 'x');
    }

    return $this->getResponse($data, $result->getLastXHeaders());
  }

  private function xShare(NodeInterface $node): TwitterOAuth {
    $socialsMessage = $this->socialTasks->getSocialsMessage($node);
    $absoluteUrl = $this->socialTasks->getSocialShareUrl($node);

    $status = $socialsMessage . ' ' . $absoluteUrl;

    // v2 needs a json now and the tweet goes to "text"
    $tweetParams['text'] = $status;

    $connection = new TwitterOAuth(
      $this->config->getConsumerKey(),
      $this->config->getConsumerSecret(),
      $this->config->getAccessToken(),
      $this->config->getAccessTokenSecret(),
    );

    $result = $connection->post("tweets", $tweetParams);
    $detail =  is_object($result) && isset($result->detail) ? $result->detail : '';

    // get X headers to check limits
    $xHeaders = $connection->getLastXHeaders();
    if ($connection->getLastHttpCode() !== 201) {
      $errorArray = [
          'error_message' => $detail,
        ] + $xHeaders;

      $this->logger->error(
        'Error: %error',
        ['%error' => print_r($errorArray, TRUE)]
      );

      throw new HttpException($connection->getLastHttpCode(), $detail, headers: $xHeaders);

    }

    return $connection;
  }

  /**
   * @param array<string, int|string|null> $data
   * @param array<string, string> $headers
   */
  private function getResponse(array $data, array $headers): JsonResponse {
    $response = ResponseBuilder::make($data)->json();
    $response->headers->add($headers);

    return $response;
  }

}
