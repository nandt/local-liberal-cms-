<?php

namespace Drupal\liberal_podcasts\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class LiberalPodcastsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'liberal_podcasts_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['liberal_podcasts.settings'];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('liberal_podcasts.settings');

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Podcasts Settings'),
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
   * {@inheritdoc}
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('liberal_podcasts.settings');

    // Save settings.
    $config->set('api_key_account_cf', $form_state->getValue('api_key_account_cf'))->save();
    parent::submitForm($form, $form_state);
  }

}
