<?php
namespace Drupal\liberal_stocks_app\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class LiberalStocksAppSettingsForm extends ConfigFormBase
{
    /**
     * Config settings.
     *
     * @var string
     */
  public const string SETTINGS = 'liberal_stocks_app.settings';

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
      return 'liberal_stocks_app_admin_settings';
    }

  /**
   * @return array<string>
   */
    protected function getEditableConfigNames(): array
    {
        return [
          static::SETTINGS,
        ];
    }

  /**
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
    #[\Override]
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
      $config = $this->config(static::SETTINGS);

      $form['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#default_value' => $config->get('username'),
      ];

      $form['password'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Password'),
        '#default_value' => $config->get('password'),
      ];

      return parent::buildForm($form, $form_state);
    }

  /**
   * @param array<mixed> $form
   * @param-out array<mixed> $form
   */
    #[\Override]
    public function submitForm(array &$form, FormStateInterface $form_state): void {
      // Retrieve the configuration.
      $this->configFactory->getEditable(static::SETTINGS)
        // Set the submitted configuration setting.
        ->set('username', $form_state->getValue('username'))
        ->set('password', $form_state->getValue('password'))
        ->save();

      parent::submitForm($form, $form_state);
    }

}
