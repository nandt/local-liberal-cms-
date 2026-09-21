<?php

declare(strict_types=1);

namespace Drupal\unicorn_socials_post\Forms;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class UnicornSocialsPostSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getFormId(): string {
    return 'unicorn_socials_post_settings_form';
  }

  /**
   * @return array<string>
   */
  #[\Override]
  protected function getEditableConfigNames(): array {
    return ['unicorn_socials_post.settings'];
  }

  /**
   * @param array<string, mixed> $form
   * @param FormStateInterface $form_state
   * @return array<string, mixed>
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('unicorn_socials_post.settings');

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Facebook and X Settings'),
    ];

    $form['facebook_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Facebook'),
      '#open' => FALSE,
    ];

    $form['facebook_settings']['facebook_page_id'] = [
      '#type' => 'textfield',
      '#title' => 'Page ID',
      '#default_value' => $config->get('facebook_page_id'),
      '#description' => $this->t('The ID of the Facebook page to post'),
      '#size' => 50,
    ];

    $form['facebook_settings']['facebook_access_token'] = [
      '#type' => 'textarea',
      '#title' => 'Access Token',
      '#default_value' => $config->get('facebook_access_token'),
      '#description' => $this->t('The Long lived access token'),
      '#rows' => 8,
    ];

    $form['facebook_settings']['facebook_app_secret'] = [
      '#type' => 'textfield',
      '#title' => 'App Secret',
      '#default_value' => $config->get('facebook_app_secret'),
      '#size' => 50,
    ];

    $form['facebook_settings']['facebook_app_id'] = [
      '#type' => 'textfield',
      '#title' => 'App ID',
      '#default_value' => $config->get('facebook_app_id'),
      '#size' => 50,
    ];

    $form['x_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('X'),
      '#open' => FALSE,
    ];

    $form['x_settings']['x_consumer_key'] = [
      '#type' => 'textfield',
      '#title' => 'Consumer Key',
      '#default_value' => $config->get('x_consumer_key'),
      '#size' => 50,
    ];

    $form['x_settings']['x_consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => 'Consumer Secret',
      '#default_value' => $config->get('x_consumer_secret'),
      '#size' => 50,
    ];

    $form['x_settings']['x_access_token'] = [
      '#type' => 'textfield',
      '#title' => 'Access Token',
      '#default_value' => $config->get('x_access_token'),
      '#size' => 100,
    ];

    $form['x_settings']['x_access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => 'Access Token Secret',
      '#default_value' => $config->get('x_access_token_secret'),
      '#size' => 100,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;

  }

  /**
   * @param array<string, mixed> $form
   * @param-out array<mixed> $form
   * @param FormStateInterface $form_state
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('unicorn_socials_post.settings');

    // Save settings.
    $config->set('facebook_app_id', $form_state->getValue('facebook_app_id'))
      ->set('facebook_app_secret', $form_state->getValue('facebook_app_secret'))
      ->set('facebook_access_token', $form_state->getValue('facebook_access_token'))
      ->set('facebook_page_id', $form_state->getValue('facebook_page_id'))
      ->set('x_consumer_key', $form_state->getValue('x_consumer_key'))
      ->set('x_consumer_secret', $form_state->getValue('x_consumer_secret'))
      ->set('x_access_token', $form_state->getValue('x_access_token'))
      ->set('x_access_token_secret', $form_state->getValue('x_access_token_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
