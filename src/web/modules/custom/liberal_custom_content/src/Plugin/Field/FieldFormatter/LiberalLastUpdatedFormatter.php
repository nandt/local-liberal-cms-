<?php

namespace Drupal\liberal_custom_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeFormatterBase;
use Drupal\liberal_custom_content\Utils\liberalCustomContentUtils;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'Liberal Last Updated' formatter for 'datetime' fields.
 */
#[FieldFormatter(
  id: 'liberal_last_updated',
  label: new TranslatableMarkup('Liberal Last Updated'),
  field_types: [
    'datetime',
  ],
)]
class LiberalLastUpdatedFormatter extends DateTimeFormatterBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function defaultSettings() {
    return [
      'date_format' => 'd/m/Y H:i',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   * @return mixed[]
   */
  #[\Override]
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $date = $item->date;
      $output = [];
      if (!empty($date)) {
        $entity = $items->getEntity();
        $site_timezone_name = $this->getSetting('timezone_override') ?: 'Europe/Athens';
        $site_timezone = new \DateTimeZone($site_timezone_name);
        $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

        // created date for compare
        $created_dateTime = DrupalDateTime::createFromTimestamp($entity->getCreatedTime(), $timezone);
        $created_dateTime->setTimezone($site_timezone);
        $created_compare = $created_dateTime->format('d/m/Y');

        $liberal_simple_format = liberalCustomContentUtils::isSimpleFormatLastUpdated(
          $created_compare,
          $date->format('d/m/Y',
          $site_timezone_name)
        );

        $output = $this->buildDate($date, $liberal_simple_format);
      }

      $elements[$delta] = $output;
    }

    return $elements;
  }

  /**
   * Creates a render array from a date object.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   * @param bool $liberal_simple_format
   *  flag if it's simple format or not.
   *
   * @return array
   *   A render array.
   */
  #[\Override]
  protected function buildDate(DrupalDateTime $date, $liberal_simple_format = FALSE): array {
    $this->setTimeZone($date);

    $build = [
      '#markup' => $this->formatDate($date, $liberal_simple_format),
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Creates a render array from a date object with ISO date attribute.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   * @param bool $liberal_simple_format
   * flag if it's simple format or not.
   *
   * @return array
   */
  protected function formatDate($date, $liberal_simple_format = FALSE) {
    $format = $liberal_simple_format ? liberalCustomContentUtils::teleytaiaEnimerosiFormat : $this->getSetting('date_format');
    $timezone = $this->getSetting('timezone_override') ?: 'Europe/Athens';
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date/time format'),
      '#description' => $this->t('See <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank">the documentation for PHP date formats</a>.'),
      '#default_value' => $this->getSetting('date_format'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $date = new DrupalDateTime();
    $this->setTimeZone($date);
    $summary[] = $date->format($this->getSetting('date_format'), $this->getFormatSettings());

    return $summary;
  }

}
