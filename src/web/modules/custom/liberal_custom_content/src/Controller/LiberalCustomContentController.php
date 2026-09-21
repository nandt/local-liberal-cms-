<?php

namespace Drupal\liberal_custom_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;

class LiberalCustomContentController extends ControllerBase {

  const staticPagesCacheContexts = [
    'languages:' . LanguageInterface::TYPE_INTERFACE,
    'user.permissions',
    'user.roles',
    'route',
    'url.query_args',
    'theme',
  ];

  public function renderBettingSlip(): array {

    $title = '<div class="taxonomy--page"><div class="section__title py-3 text-center">Κουπόνι Πάμε Στοίχημα</div></div>';

    $build = [
      '#type' => 'inline_template',
      '#template' => $title . '<div style="width:100%;text-align:right;line-height:11px;"><iframe frameborder="0"
        height="680px" id="infobeto" name="infobeto" scrolling="auto"
        src="{{ url }}"
        width="100%"></iframe>
        <p align="right">Powered by <a href="https://www.infobeto.com" target="_blank" title="Στοίχημα">infobeto.com</a></p>
        </div>',
      '#context' => [
        'url' => 'https://www.infobeto.com/tools/kouponip.php?url=https://www.liberal.gr&amp;pop_games=1&amp;str_picks=1&amp;best_per=1',
      ],
    ];

    return $build;
  }

  public function liveAseMarket(): array {
    $build = [
      '#markup' => '',
      '#cache' => [
        'tags' => ['node_list'],
        'max-age' => Cache::PERMANENT,
        'contexts' => self::staticPagesCacheContexts,
      ],
    ];

    return $build;
  }

  public function frontPage(): array {
    $build = [
      '#markup' => '',
      '#cache' => [
        'tags' => ['node_list'],
        'max-age' => Cache::PERMANENT,
        'contexts' => self::staticPagesCacheContexts,
      ],
    ];

    return $build;
  }

  public function liberalMarkets(): array {
    $build = [
      '#markup' => '',
      '#cache' => [
        'tags' => ['node_list'],
        'max-age' => Cache::PERMANENT,
        'contexts' => self::staticPagesCacheContexts,
      ],
    ];

    return $build;
  }

}
