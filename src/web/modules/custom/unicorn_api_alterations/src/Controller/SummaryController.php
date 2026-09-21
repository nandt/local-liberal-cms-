<?php

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SummaryController extends ControllerBase {

  /**
   * POST /customapi/ai/summary
   *   body: { "text": "...", "lang": "el", "max_words": 60 }
   *
   * Καλεί OpenAI Chat Completions αν OPENAI_API_KEY env exists.
   * Αν όχι (πχ local χωρίς key), fallback σε naive 2-sentence cut.
   */
  public function generate(Request $request): JsonResponse {
    // GET (από browser) → επιστροφή usage info αντί 405.
    if ($request->getMethod() === 'GET') {
      return new JsonResponse([
        'endpoint' => 'POST /customapi/ai/summary',
        'usage' => [
          'method' => 'POST',
          'headers' => ['Content-Type' => 'application/json'],
          'body' => ['text' => '<article body>', 'lang' => 'el', 'max_words' => 60],
        ],
        'example' => [
          'curl' => 'curl -X POST http://drupal/customapi/ai/summary -H "Content-Type: application/json" -d \'{"text":"Lorem ipsum dolor sit amet, consectetur adipiscing elit.","lang":"el","max_words":40}\'',
        ],
        'config' => [
          'OPENAI_API_KEY' => getenv('OPENAI_API_KEY') ? 'configured' : 'missing (fallback in use)',
          'OPENAI_MODEL' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
        ],
      ]);
    }

    $payload = json_decode($request->getContent(), TRUE) ?: [];
    $text = trim((string) ($payload['text'] ?? ''));
    $lang = (string) ($payload['lang'] ?? 'el');
    $maxWords = max(20, min(200, (int) ($payload['max_words'] ?? 60)));

    if ($text === '') {
      return new JsonResponse(['error' => 'text is required'], 400);
    }

    $clean = trim(strip_tags($text));
    $words = str_word_count($clean);

    $apiKey = getenv('OPENAI_API_KEY') ?: '';
    if ($apiKey === '') {
      return new JsonResponse([
        'summary' => $this->naiveSummary($clean),
        'words' => $words,
        'source' => 'fallback',
        'note' => 'OPENAI_API_KEY not configured — returning naive 2-sentence cut.',
      ]);
    }

    try {
      $summary = $this->openAiSummary($apiKey, $clean, $lang, $maxWords);
      return new JsonResponse([
        'summary' => $summary,
        'words' => $words,
        'source' => 'openai',
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('unicorn_api_alterations')->error('AI summary failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'summary' => $this->naiveSummary($clean),
        'words' => $words,
        'source' => 'fallback',
        'note' => 'OpenAI call failed (' . $e->getMessage() . ') — returning naive cut.',
      ]);
    }
  }

  /**
   * Calls OpenAI Chat Completions API. Returns the summary string.
   *
   * @throws GuzzleException
   * @throws \RuntimeException on bad/empty response
   */
  private function openAiSummary(string $apiKey, string $text, string $lang, int $maxWords): string {
    $model = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
    $langName = $lang === 'el' ? 'Greek' : 'English';
    $systemPrompt = sprintf(
      'You write concise news article summaries in %s. Output PLAIN TEXT only, no markdown, no headers. Maximum %d words. Match the article tone.',
      $langName,
      $maxWords
    );

    $client = \Drupal::httpClient();
    $res = $client->post('https://api.openai.com/v1/chat/completions', [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'model' => $model,
        'temperature' => 0.3,
        'max_tokens' => $maxWords * 4,
        'messages' => [
          ['role' => 'system', 'content' => $systemPrompt],
          ['role' => 'user', 'content' => $text],
        ],
      ],
      'timeout' => 30,
    ]);

    $data = json_decode((string) $res->getBody(), TRUE) ?: [];
    $summary = trim($data['choices'][0]['message']['content'] ?? '');
    if ($summary === '') {
      throw new \RuntimeException('Empty response from OpenAI');
    }
    return $summary;
  }

  /**
   * Naive fallback: first 2 sentences, max 280 chars.
   */
  private function naiveSummary(string $clean): string {
    $sentences = preg_split('/(?<=[.!?])\s+/u', $clean) ?: [];
    $summary = implode(' ', array_slice($sentences, 0, 2));
    return mb_substr($summary, 0, 280);
  }
}
