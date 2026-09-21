<?php

namespace Drupal\unicorn_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_ai\Configuration\AiConfig;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\ClientInterface;

class UnicornAiSummaryTranslation extends ControllerBase {

  public function __construct(
    private readonly AiConfig $aiConfig,
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Summarize, translate or summarize and translate text
   * using AI
   */
  public function getSummaryTranslation(string $type, Request $request): JsonResponse {
    // Get the text to process
    $text = $request->getContent();
    $text = json_decode($text, true);
    $text['text'] = strip_tags((string) $text['text'],'<p><br>');

    // Set the headers
    $headers = $this->getSummaryTranslationHeaders();

    $prompt_role = $this->aiConfig->prompts[$type]['prompt_role'];
    $prompt_role = str_replace('[PUBLICATION_NAME]', $this->aiConfig->publicationName, $prompt_role);
    $prompt_query = $this->aiConfig->prompts[$type]['prompt_query'];

    $data = $this->getRequestBodySummaryTranslation($this->aiConfig->model, $prompt_role, $prompt_query, $text['text']);

    try {
      $response = $this->httpClient->request(
        "POST",
        $this->aiConfig->endpointUrl,
        [
          "headers" => $headers,
          "body" => json_encode($data),
          "timeout" => 240
        ]
      );

      $response_body = $response->getBody()->getContents();
      $response_body = json_decode($response_body);

      // Notify client that streaming has finished
      return new JsonResponse($response_body, 200);
    }
    catch (Exception $e) {
      return new JsonResponse(['error' => 'Failed to get response from the api: ' . $e->getMessage()], 500);
    }
  }

  /**
   * @return array<string, string>
   */
  private function getSummaryTranslationHeaders(): array {
    return [
      "Content-Type" => "application/json",
      "Authorization" => "Bearer " . $this->aiConfig->openAiKey,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function getRequestBodySummaryTranslation(string $model, string $prompt_role, string $prompt_query, string $text): array {
    return [
      'model' => $model,
      'messages' => [
        [
          'role' => 'system',
          'content' => $prompt_role
        ],
        [
          'role' => 'user',
          'content' => $prompt_query
        ],
        [
          'role' => 'system',
          'content' => $text
        ],
      ]
    ];
  }

}
