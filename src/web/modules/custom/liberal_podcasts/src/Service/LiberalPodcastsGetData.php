<?php

namespace Drupal\liberal_podcasts\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class LiberalPodcastsGetData {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function getData(string $video_id): ?array {

    try {
      $response = $this->makeGuzzleRequest($video_id);
    }
    catch (ClientExceptionInterface) {
      throw new BadRequestException('Could not retrieve the video data.');
    }

    $data = Json::decode($response->getBody());
    $data = $data['result'] ?? NULL;

    if (!$data) {
      return NULL;
    }
    return [
      'uid' => $data['uid'],
      'duration' => $data['duration'],
      'thumbnail' => $data['thumbnail'],
      'meta' => $data['meta'],
      'uploaded' => $data['uploaded'],
      'size' => $data['size'],
      'input' => $data['input'],
      'width' => $data['input']['width'],
      'height' => $data['input']['height'],
    ];
  }

  private function makeGuzzleRequest($video_id) {
    $config = $this->configFactory->get('liberal_podcasts.settings');
    $authorization = $config->get('api_key_account_cf');
    $client_response = $this->httpClient->request(
          'GET',
          "https://api.cloudflare.com/client/v4/accounts/0f1a930b16ca04f07cb2e429e3ffd70e/stream/{$video_id}",
          [
            'headers' => [
              'Authorization' => "Bearer {$authorization}",
              'Accept' => 'application/json',
            ],
            "timeout" => 240,
          ]
        );

    return $client_response;
  }

}
