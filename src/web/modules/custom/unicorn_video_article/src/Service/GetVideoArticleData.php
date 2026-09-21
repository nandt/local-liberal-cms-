<?php

namespace Drupal\unicorn_video_article\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\unicorn_core\Accessors\Data;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Drupal\unicorn_video_article\Configuration\VideoArticleConfig;
use Drupal\unicorn_video_article\Dto\VideoArticleDto;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class GetVideoArticleData {

  private LoggerInterface $logger;

  public function __construct(
    private ClientInterface $httpClient,
    private VideoArticleConfig $videoArticleConfig,
    private ExtendedValidator $extendedValidator,
    LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    $this->logger = $loggerChannelFactory->get('unicorn_video_article');
  }

  public function get(string $videoId): ?VideoArticleDto {

    $response = $this->sendRequest($videoId);
    $data = Json::decode($response->getBody());

    $result = Data::get($data, 'result');

    if (!$result) {
      return NULL;
    }

    $dto = VideoArticleDto::fromApiResponse($result);
    $this->extendedValidator->validateAndThrow($dto);

    return $dto;
  }

  /**
   * @throws Throwable
   */
  private function sendRequest(string $videoId): ResponseInterface {
    try {
      $response = $this->httpClient->request(
        'GET',
        $this->videoArticleConfig->getStreamApiUrl() . $videoId,
        [
          'headers' => [
            'Authorization' => "Bearer {$this->videoArticleConfig->getAuthKey()}",
            'Accept' => 'application/json',
          ],
          "timeout" => 240,
        ]
      );
    }
    catch (Throwable $e) {
      $this->logger->error($e->getMessage());
      throw $e;
    }

    return $response;
  }

}
