<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * @phpstan-consistent-constructor
 */
class UnicornSocialsPostTokensHooks {

    public function __construct(
      private readonly ConfigFactoryInterface $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Hook('token_info')]
    public function tokenInfo(): array {
      $info = [];

      $info['types']['unicorn_social_share'] = [
        'name' => t('Unicorn Social Share Tokens'),
        'description' => t('Unicorn social share tokens.'),
      ];

      $info['tokens']['unicorn_social_share']['facebook-app-id'] = [
        'name' => t("Facebook App ID"),
        'description' => t('Facebook App ID'),
        'type' => 'string'
      ];

      return $info;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $tokens
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    #[Hook('tokens')]
    public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {

      if ($type !== 'unicorn_social_share') {
        return [];
      }

      $replacements = [];
      foreach ($tokens as $name => $original) {
          switch ($name) {
              case 'facebook-app-id':
                $config_social = $this->config->get('unicorn_socials_post.settings');
                $replacements[$original] = $config_social->get('facebook_app_id');
              break;
          }
      }

      return $replacements;
    }

}
