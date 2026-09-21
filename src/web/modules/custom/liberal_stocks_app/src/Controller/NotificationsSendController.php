<?php

namespace Drupal\liberal_stocks_app\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\liberal_stocks_app\Configuration\NotificationsConfig;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_socials_post\Resources\SocialsPostResource;
use Drupal\unicorn_socials_post\Services\SocialTasks;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class NotificationsSendController extends ControllerBase {

  private const int GUZZLE_RESPONSE_TIMEOUT = 10;
  private const int GUZZLE_CONNECTION_TIMEOUT = 10;

  protected string $accessToken;

  private readonly LoggerInterface $logger;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly SocialTasks $socialTasks,
    private readonly SocialsPostResource $socialsPostResource,
    private readonly NotificationsConfig $notificationsConfig,
    LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
    $this->logger = $loggerChannelFactory->get('send_notifications');
  }

  /**
   * @throws Throwable
   */
  public function sendNotification(NodeInterface $node): JsonResponse {

    if ($this->notificationsConfig->hasMissingValue()) {
      throw new BadRequestHttpException("Missing configuration values");
    }

    $this->accessToken = $this->getAccessToken();
    $this->sendRequest((int) $node->id());

    $values = $this->socialTasks->updateCounters($node, "notifications");

    $data = $values ? $this->socialsPostResource->toDashboardApiSingle($values, 'notifications') : [];
    return ResponseBuilder::make($data)->json();
  }

  /**
   * @throws Throwable
   */
  private function getAccessToken(): string {

    try {
      $response = $this->httpClient->request(
        'POST',
        $this->notificationsConfig->getLoginEndpoint(),
        [
          'json' => [
            "username" => $this->notificationsConfig->getUsername(),
            "password" => $this->notificationsConfig->getPassword()
          ],
          'timeout' => self::GUZZLE_RESPONSE_TIMEOUT,
          'connection_timeout' => self::GUZZLE_CONNECTION_TIMEOUT
        ]
      );

      $data = json_decode($response->getBody(), true);
      return $data['accessToken'];
    }
    catch (Throwable $e) {
      $this->logger->error($e->getMessage());
      throw $e;
    }
  }

  /**
   * @throws Throwable
   */
  private function sendRequest(int $nid): void {
   try {
        $response = $this->httpClient->request(
        'POST',
        $this->notificationsConfig->getNotificationsEndpoint(),
          [
            "headers" => [
              "Content-Type" => "application/json",
              "Authorization" => "Bearer " . $this->accessToken,
            ],
            "json" => ["nid" => $nid],
            "timeout" => self::GUZZLE_RESPONSE_TIMEOUT,
            "connection_timeout" => self::GUZZLE_CONNECTION_TIMEOUT
          ]
        );
      }
      catch (Throwable $e) {
        $message = t("Error: %message", ["%message" => $e->getMessage()]);
        $this->logger->error($message);
        throw $e;
      }

    if ($response->getStatusCode() !== Response::HTTP_OK) {
      $this->logger->error($response->getBody());

      throw new HttpException($response->getStatusCode(), $response->getBody());
    }
  }
}
