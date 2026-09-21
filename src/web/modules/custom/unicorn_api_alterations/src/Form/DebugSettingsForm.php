<?php

namespace Drupal\unicorn_api_alterations\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class DebugSettingsForm extends FormBase {

  public function getFormId(): string {
    return 'liberal_debug_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $enabled = (bool) \Drupal::state()->get('liberal.debug_mode_enabled', FALSE);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Liberal3 debug mode'),
      '#description' => $this->t('Όταν ενεργό, οι Next.js API errors καταγράφονται σε error_log.log στο app-web container.'),
      '#default_value' => $enabled,
    ];

    $form['info'] = [
      '#type' => 'item',
      '#markup' => $this->t('Status: <strong>@s</strong>', ['@s' => $enabled ? 'ENABLED' : 'DISABLED']),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    \Drupal::state()->set('liberal.debug_mode_enabled', (bool) $form_state->getValue('enabled'));
    $this->messenger()->addStatus($this->t('Debug mode updated.'));
  }

}
