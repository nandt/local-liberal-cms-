<?php

namespace Drupal\unicorn_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class UnicornAiAdminForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'unicorn_ai_admin_settings';
  }

  /**
   * @return array<int, string>
   */
  protected function getEditableConfigNames(): array {
    return ['unicorn_ai.admin_settings'];
  }

  /**
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('unicorn_ai.admin_settings');

    $form['openai_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('OpenAI Key'),
      '#rows' => 4,
      '#default_value' => $config->get('openai_key') ?? ''
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;
  }

  /**
   * @param array<string, mixed> $form
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('unicorn_ai.admin_settings');
    $config->set('openai_key', $form_state->getValue('openai_key'));

    $config->save();
  }
}
