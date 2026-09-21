<?php

namespace Drupal\liberal_sitemap_generator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\liberal_sitemap_generator\Controller\LiberalSitemapGeneratorController;

class LiberalSitemapGeneratorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'liberal_sitemap_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // previous config
    $config = \Drupal::config('liberal_sitemap_generator.settings');

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Generate Sitemaps on demand'),
    ];

    $form['batch'] = [
      '#type' => 'select',
      '#title' => 'Choose sitemap to generate',
      '#empty_value' => '',
      '#empty_option' => $this->t('None'),
      '#options' => [
        'news' => $this->t('News Sitemap'),
        'articles' => $this->t('Articles Sitemap'),
        'index' => $this->t('Sitemap Index'),
        'stocks' => $this->t('Stocks Sitemap'),
        'indices' => $this->t('Indices Sitemap'),
        'index_xrimatistirio' => $this->t('Index Xrimatistirio'),
      ],
    ];

    $form['date_option'] = [
      '#type' => 'select',
      '#title' => 'Date option for articles sitemap',
      '#empty_value' => '',
      '#empty_option' => 'None',
      '#options' => [
        'today' => 'Today',
        'month-current' => 'Current Month',
        'month-previous' => 'Previous month',
        'month' => 'Month',
        'month-to-now' => 'Month to Now',
        'all' => 'All',
      ],
      "#description" => 'Selecting "All" will create every single sitemap
      from the very first one so take care. Applicable only for "Articles Sitemap"',
    ];

    $form['exclude_current_month'] = [
      '#type' => 'checkbox',
      '#title' => 'Exclude Current Month',
      '#description' => 'Only relevant if the option is "Month to Now" or "All". Applicable only for "Articles Sitemap"',
    ];

    $form['daily_sitemaps_housekeeping'] = [
      '#type' => 'checkbox',
      '#title' => 'Daily Sitemaps Housekeeping',
      '#default_value' => !empty($config->get('daily_sitemaps_housekeeping')) ? 1 : 0,
      '#description' => 'CAUTION. This option should only be enabled if we have
      a "daily" sitemap strategy, otherwise it will add unnecessary load to to sitemap index creation!
      Delete daily sitemap when the month changes. Applicable only for the Sitemap index command',
    ];

    $form['date'] = [
      '#type' => 'textfield',
      '#title' => 'Date for the sitemap generation of articles',
      '#size' => 10,
    ];

    $form['batch_max_links'] = [
      '#type' => 'textfield',
      '#title' => 'Max links per file for Article Sitemaps',
      '#default_value' => !empty($config->get('batch_max_links')) ? $config->get('batch_max_links') : 5000,
      '#size' => 10,
      '#required' => TRUE,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $batch = $form_state->getValue('batch');

    $options['date'] = $form_state->getValue('date') ?? NULL;
    $options['date_option'] = $form_state->getValue('date_option') ?? NULL;
    $options['exclude_current_month'] = !empty($form_state->getValue('exclude_current_month')) ? TRUE : NULL;

    if (!empty($batch)) {
      $generate = new LiberalSitemapGeneratorController();
      $generate->setExecuteBatch($batch, $options, 'form');
    }

    $config = \Drupal::service('config.factory')->getEditable('liberal_sitemap_generator.settings');
    $config->set('batch_max_links', $form_state->getValue('batch_max_links'));
    $config->set('daily_sitemaps_housekeeping', $form_state->getValue('daily_sitemaps_housekeeping'));
    $config->save();
  }

  #[\Override]
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (is_int($form_state->getValue('batch_max_links'))) {
      $form_state->setErrorByName('batch_max_links', $this->t('Please insert an integer'));
    }
  }

}
