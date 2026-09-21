<?php

declare(strict_types=1);

namespace Drupal\unicorn_ai\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;

final readonly class AiConfig {
  public ?string $openAiKey;
  public string $publicationName;
  public string $model;
  public string $endpointUrl;

  /**
   * @var array<string, array<string, string>>
   */
  public array $prompts;

  public function __construct(ConfigFactoryInterface $configFactory) {
    $config = $configFactory->get('unicorn_ai.admin_settings');
    $this->openAiKey = $config->get('openai_key') ?: null;
    $this->publicationName = 'liberal.gr';
    $this->model = 'gpt-4o-mini';
    $this->endpointUrl = 'https://api.openai.com/v1/chat/completions';
    $this->prompts = [
      'summarize' => [
        'prompt_role' => 'Είσαι ένας συντάκτης του [PUBLICATION_NAME]',
        'prompt_query' => 'Φτιάξε τη σύνοψη του άρθρου που θα σου δώσω στην συνέχεια σε 50 με 160 το πολύ λέξεις στα ελληνικά για χρήση στην εφημερίδα.',
      ],
      'translate' => [
        'prompt_role' => 'Είσαι ένας συντάκτης του [PUBLICATION_NAME]',
        'prompt_query' => 'Μετέφρασε το άρθρο που θα σου δώσω στα ελληνικά για χρήση στην εφημερίδα. Διατήρησε τον αριθμό λέξεων στο ίδιο επίπεδο με το άρθρο που θα σου παρέχω. Βάλε παραγράφους με τη μορφή HTML p tags.',
      ],
      'summarize_translate' => [
        'prompt_role' => 'Είσαι ένας συντάκτης του [PUBLICATION_NAME]',
        'prompt_query' => 'Μετέφρασε το άρθρο που θα σου δώσω στα ελληνικά για χρήση στην εφημερίδα. Διατήρησε τον αριθμό λέξεων στο ίδιο επίπεδο με το άρθρο που θα σου παρέχω. Στη συνέχεια φτιάξε τη σύνοψη του μεταφρασμένου άρθρου που έφτιαξες σε 50 με 160 το πολύ λέξεις στα ελληνικά. Βάλε στη μετάφραση παραγράφους με τη μορφή HTML p tags, Διαχώρισε τη μετάφραση από τη σύνοψη χρησιμοποιώντας τις λέξεις [TRANSLATION] και [SUMMARY]',
      ],
    ];
  }

}
