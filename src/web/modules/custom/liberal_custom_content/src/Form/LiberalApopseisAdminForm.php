<?php

namespace Drupal\liberal_custom_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LiberalApopseisAdminForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Number of opinion regions
   *
   * @var int
   */
  const APOPSEIS_REGIONS = 9;

  /**
   * file upload limit in bytes
   *
   * @var int
   */
  const fileSizeLimit = 1048576;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'liberal_apopseis_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['liberal_custom_content.apopseis_settings'];
  }

  /**
   * @var int
   */

  const NUMBER_OF_REGIONS = 9;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('liberal_custom_content.apopseis_settings');

    // add libraries needed for the form to function
    $form['#attached']['library'][] = 'liberal_custom_content/apopseis-form';

    $formDescription = $this->t("Select the desired author or category to be displayed in each region");
    $authors_list = $this->getAuthorList();

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="general-form-description">' . Markup::create($formDescription) . '</div>',
    ];

    // fieldset settings
    for ($i = 1; $i <= self::NUMBER_OF_REGIONS; $i++) {
      $form['apopseis_region_' . $i . '_fieldset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Opinions Region ' . $i . ' Settings'),
        '#tree' => TRUE,
        '#attributes' => [
          'class' => ['custom-fieldset-handler'],
        ],
      ];

      $form['apopseis_region_' . $i . '_fieldset']['apopseis_region'] = [
        '#type' => 'select',
        '#title' => 'Category',
        '#options' => $authors_list,
        '#default_value' => $config->get('apopseis_region_' . $i . '_fieldset')['apopseis_region'] ?? array_key_first($authors_list),
        '#wrapper_attributes' => ['class' => ['container-inline']],
      ];

      $form['apopseis_region_' . $i . '_fieldset']['apopseis_region_option'] = [
        '#type' => 'select',
        '#title' => 'Section Opinions',
        '#options' => [
          'author' => $this->t('Author'),
          'category' => $this->t('Category'),
        ],
        '#default_value' => $config->get('apopseis_region_' . $i . '_fieldset')['apopseis_region_option'] ?? 'author',
      ];

      $form['apopseis_region_' . $i . '_fieldset']['file'] = [
        '#type' => 'container',
        '#states' => [
          'enabled' => [
            ':input[name="apopseis_region_' . $i . '_fieldset[apopseis_region_option]"]' => ['value' => 'category'],
          ],
        ],
        '#attributes' => [
          'class' => ['file-handler-wrap'],
        ],
      ];

      $form['apopseis_region_' . $i . '_fieldset']['file']['apopseis_region_image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Add Profile Picture'),
        '#description' => $this->t("Only 1 file.</br>File limit 1MB</br>Allowed file extensions: png, jpg, jpeg, webp"),
        '#upload_location' => 'public://apopseis-images/',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'webp png jpg jpeg'],
          'FileSizeLimit' => ['fileLimit' => self::fileSizeLimit],
        ],
        '#attributes' => [
          'class' => ['selected-file-handler'],
        ],
        '#default_value' => $config->get('apopseis_region_' . $i . '_fieldset')['file']['apopseis_region_image'] ?? [],
      ];

      $form['apopseis_region_' . $i . '_fieldset']['apopseis_region_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('author_firstname'),
        '#size' => 15,
        '#states' => [
          'readonly' => [
            ':input[name="apopseis_region_' . $i . '_fieldset[apopseis_region_option]"]' => ['value' => 'author'],
          ],
          'required' => [
            ':input[name="apopseis_region_' . $i . '_fieldset[apopseis_region_option]"]' => ['value' => 'category'],
          ],
        ],
        '#default_value' => $config->get('apopseis_region_' . $i . '_fieldset')['apopseis_region_name'] ?? '',
      ];

      $form['apopseis_region_' . $i . '_fieldset']['apopseis_region_surname'] = [
        '#type' => 'textfield',
        '#title' => $this->t('author_lastname'),
        '#size' => 15,
        '#states' => [
          'readonly' => [
            ':input[name="apopseis_region_' . $i . '_fieldset[apopseis_region_option]"]' => ['value' => 'author'],
          ],
          'required' => [
            ':input[name="apopseis_region_' . $i . '_fieldset[apopseis_region_option]"]' => ['value' => 'category'],
          ],
        ],
        '#default_value' => $config->get('apopseis_region_' . $i . '_fieldset')['apopseis_region_surname'] ?? '',
      ];

      $form['apopseis_region_' . $i . '_fieldset']['apopseis_region_status'] = [
        '#type' => 'checkbox',
        '#title' => 'Enabled',
        '#default_value' => $config->get('apopseis_region_' . $i . '_fieldset')['apopseis_region_status'] ?? TRUE,
      ];
    }

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
    $config = $this->config('liberal_custom_content.apopseis_settings');
    $apopseis_values = [];
    $block_statuses = [];
    $category_values = [];
    $old_files = [];

    // Save settings. Regions
    for ($i = 1; $i <= self::APOPSEIS_REGIONS; $i++) {
      // get existing values before setting the new ones
      $fieldset_key = 'apopseis_region_' . $i . '_fieldset';
      $block_config = $config->get($fieldset_key);

      $values = $form_state->getValue($fieldset_key);
      $config->set($fieldset_key, $values);

      // only save something if it changed
      foreach ($values as $key => $value) {
        // check if file first to handle it differently
        if ($key == 'file') {
          $values[$key] = array_shift($values[$key]);

          // for the first save
          $block_config[$key] = (isset($block_config[$key]) && is_array($block_config[$key])) ? array_shift($block_config[$key]) : [];
          $old_files['apopseis_region_' . $i . '_fieldset'] = $block_config[$key];
        }

        if (!isset($block_config[$key]) || $values[$key] !== $block_config[$key]) {
          $block_statuses['views_block__apopseis_region_' . $i . '_block_1'] = $values['apopseis_region_status'];
          $apopseis_values['apopseis_region_' . $i] = $values['apopseis_region'];
          $category_values['apopseis_region_' . $i . '_fieldset'] = $values;
        }
      }
    }

    $config->save();

    // disable blocks if any
    if (!empty($block_statuses)) {
      $this->disableBlocks($block_statuses);
    }

    // change the necessary views filters
    if (!empty($apopseis_values)) {
      $this->changeApopseisViews($apopseis_values);
    }

    if (!empty($category_values)) {
      $this->fileDataSave($category_values, $old_files);
    }
  }

  private function changeApopseisViews($apopseis) {

    foreach ($apopseis as $key => $apopsi) {
      $config = \Drupal::configFactory()->getEditable('views.view.' . $key);

      // add data integrity check to avoid some nasty data corruption
      if (!empty($config->get('display.default.display_options.filters.tid.value'))) {
        $config->set('display.default.display_options.filters.tid.value', [$apopsi]);
        // Save the changes.
        $config->save();
      }
    }
  }

  private function disableBlocks($block_statuses) {
    $configFactory = \Drupal::configFactory();

    foreach ($block_statuses as $key => $block_status) {
      $blockConfig = $configFactory->getEditable('block.block.' . $key);
      $blockConfig->set('status', $block_status);
      $blockConfig->save();
    }
  }

  /**
   * @param array $values
   * @param array $old_files
   * Detect any changes to the saved files and update as necessary
   */
  private function fileDataSave($values, $old_files) {

    foreach ($values as $key => $value) {

      if (isset($old_files[$key]) && is_array($old_files[$key])) {
        $previous_file = array_shift($old_files[$key]);
      }
      elseif (isset($old_files['file'])) {
        $previous_file = $old_files['file'];
      }

      $current_file_id = is_array($value['file']) ? array_shift($value['file']) : $value['file'];
      // if file id is still an array shift again...
      if (is_array($current_file_id)) {
        $current_file_id = array_shift($current_file_id);
      }

      if (!empty($previous_file) && $previous_file != $current_file_id) {
        $previous_file = $this->entityTypeManager->getStorage('file')->load($previous_file);

        if ($previous_file instanceof FileInterface) {
          $previous_file->delete();
        }
      }

      // set as permanent
      if (!empty($current_file_id) && $previous_file != $current_file_id) {
        $current_file = $this->entityTypeManager->getStorage('file')->load($current_file_id);
        if ($current_file instanceof FileInterface) {
          $current_file->setPermanent();
          $current_file->save();
        }
      }
    }
  }

  /**
   * Get the author list to build the options array
   *
   * @return array
   * The author list to use for the form options
   */
  private function getAuthorList() {

    $cid = 'liberalauthorlistoptions';
    $return_list = [];

    // cache the results until the vocabulary changes
    if ($cache = \Drupal::cache()->get($cid)) {
      $return_list = $cache->data;
    }
    else {
      $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $authors_list = $storage->loadTree('category', 70, 1, TRUE);
      $cache_tags = [];

      foreach ($authors_list as $author) {
        $show_to_list = $author->field_show_to_category_list->value;
        if ($show_to_list) {
          $return_list[$author->id()] = $author->getName();
        }
      }

      /** invalidate the cache when the vocabulary itself changes
       *  or any term is added, deleted or edited
       */
      $cache_tags = [
        'taxonomy_term_list:category',
      ];

      \Drupal::cache()
        ->set(
          $cid,
          $return_list,
          Cache::PERMANENT,
          $cache_tags
      );
    }

    return $return_list;
  }

}
