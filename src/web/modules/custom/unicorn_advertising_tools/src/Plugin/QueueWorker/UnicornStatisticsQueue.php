<?php

namespace Drupal\unicorn_advertising_tools\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/* Omitting "cron" from annotation means the queue won't automatically run
*  can also try time = 0 or omitting the annotation altogether, but if omitted
*  we won't be able to see our queue anywhere, in any UI, command etc.
*/
#[QueueWorker(
  id: 'unicorn_statistics_queue',
  title: new TranslatableMarkup('Unicorn Statistics Queue')
)]
class UnicornStatisticsQueue extends QueueWorkerBase {

  /**
   * @param array<string, mixed> $configuration
   * @param array<string, mixed> $plugin_definition
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    /** This function needs to exist
    *   but we will leave it empty and never execute this queue
    *   without executing it manually through a drush batch
    */
  }

}
