<?php

namespace Drupal\unicorn_api_alterations\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

// Auto-export jsonapi_extras configs σε src/config/sync/ όταν αλλάζουν από UI.
// Ενεργό μόνο σε dev/local — production δεν θέλουμε runtime DB → filesystem
// write (read-only image, prod DB δεν είναι source of truth).
final readonly class JsonapiConfigAutoExportSubscriber implements EventSubscriberInterface {

  private const string SYNC_DIR = '/opt/drupal/config/sync';

  private LoggerInterface $logger;

  public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
    $this->logger = $loggerFactory->get('unicorn_api_alterations');
  }

  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => ['onSave', -100]];
  }

  public function onSave(ConfigCrudEvent $event): void {
    $env = getenv('ENV') ?: '';
    if (!in_array($env, ['local', 'dev'], TRUE)) {
      return;
    }

    $name = $event->getConfig()->getName();

    // Map config name → filename. jsonapi_resource_config entities έχουν
    // εσωτερικό config name `jsonapi_extras.jsonapi_resource_config.X` αλλά
    // αποθηκεύονται στο sync dir χωρίς το `jsonapi_extras.` prefix (legacy
    // convention που κρατάμε για backwards compat).
    if ($name === 'jsonapi_extras.settings') {
      $filename = 'jsonapi_extras.settings';
    }
    elseif (str_starts_with($name, 'jsonapi_extras.jsonapi_resource_config.')) {
      $filename = substr($name, strlen('jsonapi_extras.'));
    }
    else {
      return;
    }

    if (!is_writable(self::SYNC_DIR)) {
      $this->logger->warning('jsonapi auto-export: SYNC_DIR not writable, skipping @name', ['@name' => $name]);
      return;
    }

    $data = $event->getConfig()->get();
    // Το uuid είναι ταυτότητα του συγκεκριμένου περιβάλλοντος. Τα configs εδώ
    // γίνονται seed ανά περιβάλλον (php:eval, hook_install, UI), οπότε τα uuid
    // αποκλίνουν και το import σκάει με "already exists with UUID ...". Ταυτότητα
    // είναι το id.
    unset($data['_core'], $data['uuid']);

    $file = self::SYNC_DIR . '/' . $filename . '.yml';
    $yaml = Yaml::dump($data, 10, 2);

    if (file_put_contents($file, $yaml) === FALSE) {
      $this->logger->error('jsonapi auto-export: failed to write @file', ['@file' => $file]);
      return;
    }

    $this->logger->info('jsonapi auto-export: wrote @file', ['@file' => $file]);
  }
}
