<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Config;

class UnicornVideoArticleSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'unicorn_video_article_settings_form';
  }

  /**
   * @return array<string>
   */
  protected function getEditableConfigNames(): array {
    return ['unicorn_video_article.settings'];
  }

  /**
   * @param array<string, mixed> $form
   * @param FormStateInterface $form_state
   * @return array<string, mixed>
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state): array
  {

    /** @var Config $config */
    $config = $this->config('unicorn_video_article.settings');

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Video Article Settings'),
    ];

    $form['cloudflare_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('API Keys'),
      '#open' => FALSE,
    ];

    $form['cloudflare_settings']['api_key_account_cf'] = [
      '#type' => 'textfield',
      '#title' => 'Account Key',
      '#default_value' => $config->get('api_key_account_cf'),
      '#description' => t('Cloudflare Account Key'),
      '#size' => 255,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;
  }

  /**
   * @param array<string, mixed> $form
   * @param-out array<string, mixed> $form
   * @param FormStateInterface $form_state
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    /** @var Config $config */
    $config = $this->config('unicorn_video_article.settings');

    // Save settings.
    $config->set('api_key_account_cf', $form_state->getValue('api_key_account_cf'))->save();
    parent::submitForm($form, $form_state); // @phpstan-ignore paramOut.type
  }

}
