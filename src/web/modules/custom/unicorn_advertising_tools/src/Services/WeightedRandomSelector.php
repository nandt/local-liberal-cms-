<?php

declare(strict_types=1);

namespace Drupal\unicorn_advertising_tools\Services;

use Drupal\unicorn_core\Support\Collection;
use LogicException;
use Random\RandomException;

/**
 * Picks one candidate at random, in proportion to its weight.
 *
 * @phpstan-type PromotedArticleRow array{
 *   entity_id: int|numeric-string,
 *   impressions: int|numeric-string,
 *   target_impressions: int|numeric-string,
 *   priority: int|numeric-string,
 *   clicks: int|numeric-string,
 *   start_date: int|numeric-string,
 *   end_date: int|numeric-string,
 *   promoted_date: int|numeric-string,
 *   history_date: int|numeric-string,
 *   state: int|numeric-string,
 * }
 */
final class WeightedRandomSelector {

  /**
   * @param Collection<int, PromotedArticleRow> $items
   *
   * @throws RandomException
   * @throws LogicException
   */
    public function select(Collection $items): int {
        $weights = $this->getWeightArray($items);
        $target = random_int(1, array_sum($weights));

        $cumulative = 0;
        $selectedKey = null;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($target <= $cumulative) {
                $selectedKey = $key;
                break;
            }
        }

        if (is_null($selectedKey)) {
            throw new LogicException('Cumulative weight never reached the target.');
        }

        return (int) $selectedKey;
    }

  /**
   * @param Collection<int, PromotedArticleRow> $items
   *
   * @return array<int>
   */
    private function getWeightArray(Collection $items): array {
        $weightedNodes = $items->mapWithKeys(fn($item, $key): array => [$key => (int) $item['priority']]);

        return $weightedNodes->toArray();
    }
}
