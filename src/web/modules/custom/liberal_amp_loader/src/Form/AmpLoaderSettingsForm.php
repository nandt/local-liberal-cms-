<?php

namespace Drupal\liberal_amp_loader\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AmpLoaderSettingsForm extends ConfigFormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'liberal_amp_loader.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'liberal_amp_loader_admin_settings';
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['ga4_measurement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GA4 Measurement ID'),
      '#default_value' => $config->get('ga4_measurement_id'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
        // Set the submitted configuration setting.
      ->set('ga4_measurement_id', $form_state->getValue('ga4_measurement_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
