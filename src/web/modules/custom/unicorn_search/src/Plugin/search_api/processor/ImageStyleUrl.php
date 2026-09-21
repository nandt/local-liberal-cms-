<?php

declare(strict_types=1);

namespace Drupal\unicorn_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\unicorn_search\Configuration\SearchConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[SearchApiProcessor(
  id: 'unicorn_search_image_style_url',
  label: new TranslatableMarkup('Image style URL'),
  description: new TranslatableMarkup('Adds the styled image derivative URL for the article image.'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class ImageStyleUrl extends ProcessorPluginBase {

  /**
   * @param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SearchConfig $searchConfig,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * @param array<string, mixed> $configuration
   */
  #[\Override]
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): self {
    /** @var \Drupal\unicorn_search\Configuration\SearchConfig $searchConfig */
    $searchConfig = $container->get(SearchConfig::class);

    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $searchConfig,
    );
  }

  #[\Override]
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource) {
      return [];
    }

    $definition = [
      'label' => $this->t('Image style URL'),
      'description' => $this->t('The styled image derivative URL for the article image.'),
      'type' => 'string',
      'processor_id' => $this->getPluginId(),
    ];

    return [$this->searchConfig->getSearchApiImageStyleUrlProperty() => new ProcessorProperty($definition)];
  }

  /**
   * @param \Drupal\search_api\Item\ItemInterface<string, \Drupal\search_api\Item\FieldInterface> $item
   */
  public function addFieldValues(ItemInterface $item): void {
    $imageStyleUrl = $this->buildImageStyleUrl($item);

    if (is_null($imageStyleUrl)) {
      return;
    }

    $field = $item->getField($this->searchConfig->getSearchApiImageStyleUrlField(), FALSE);

    if (!$field instanceof FieldInterface) {
      return;
    }

    $field->addValue($imageStyleUrl);
  }

  /**
   * @param \Drupal\search_api\Item\ItemInterface<string, \Drupal\search_api\Item\FieldInterface> $item
   */
  private function buildImageStyleUrl(ItemInterface $item): ?string {
    $entity = $item->getOriginalObject()?->getValue();

    if (!$entity instanceof NodeInterface || !$entity->hasField($this->searchConfig->getSearchApiImageField())) {
      return NULL;
    }

    $image = $entity->get($this->searchConfig->getSearchApiImageField())->entity;

    if (!$image instanceof FileInterface) {
      return NULL;
    }

    $style = $this->entityTypeManager->getStorage('image_style')->load($this->searchConfig->getSearchImageStyle());

    if (!$style instanceof ImageStyleInterface) {
      return NULL;
    }

    $fileUri = $image->getFileUri();

    if ($fileUri === NULL) {
      return NULL;
    }

    return $style->buildUrl($fileUri);
  }

}
