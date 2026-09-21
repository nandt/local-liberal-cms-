<?php

namespace Drupal\unicorn_socials_post\Services;

use Drupal\Core\Cache\CacheTagsInvalidator;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\unicorn_core\Support\Http\Request;
use InvalidArgumentException;
use Drupal\unicorn_field_mapper\Factory\FieldMapFactory;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

class SocialTasks {

    private readonly Request $request;

  /**
   * If adding a new social media etc, the first field should be
   * the "times shared" and second the "last date of sharing"
   */
  public const array SHARE = [
    'facebook' => [
      'facebook_times_shared',
      'facebook_last_sharing_date',
    ],
    'x' => [
      'x_times_shared',
      'x_last_sharing_date',
    ],
    'notifications' => [
      'notification_times_sent',
      'last_notification_date',
    ],
  ];

  public function __construct(
    private readonly Connection $connection,
    private readonly TimeInterface $time,
    private readonly FieldMapFactory $fieldMapFactory,
    private readonly AliasManagerInterface $aliasManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    RequestStack $request
  ) {
      $this->request = Request::createFromRequest(
        $request->getCurrentRequest() ?? throw new RuntimeException('No current request available.'));
  }

  /**
   * @return array<string, string>|false
   */
  public function updateCounters(NodeInterface $node, string $share): array|false {

    $dbFields = self::SHARE[$share] ?? null;

    if (!$dbFields) {
      throw new InvalidArgumentException("Unsupported social network: $share");
    }

    $values = false;
    $time = $this->time->getRequestTime();
    $addCount = 1;
    $nid = $node->id();

    $result = $this->connection->merge('unicorn_social_sharing')
    ->key('entity_id', $nid)
    ->fields([
      $dbFields[0] => $addCount,
      $dbFields[1] => $time
    ])
    ->expression($dbFields[0], "[{$dbFields[0]}] + :new_share", [':new_share' => $addCount])
    ->execute();

    // TODO: avoid cache invalidation if possible. But we need to show fresh data in dashboard
    $this->cacheTagsInvalidator->invalidateTags($node->getCacheTags());

    if($result) {
      $values = $this->connection->select('unicorn_social_sharing', 'uss')
        ->fields('uss', [$dbFields[0], $dbFields[1], 'entity_id'])
        ->condition('entity_id', $nid)
        ->execute();
    }



    return !$values ? [] : $values->fetchAssoc();
  }

  /**
   * The text that will be shared to social media.
   */
  public function getSocialsMessage(NodeInterface $node): string {
    $message = '';

    $hyperTitle = $this->fieldMapFactory->forEntity($node)->getField('subtitle')?->value;
    if(!empty($hyperTitle)) {
      $message = strip_tags((string) $hyperTitle) . ' - ';
      $message = str_replace(PHP_EOL,"", $message);
    }

    $title = $node->getTitle();
    $message .= $title;
    return $message;
  }

  /**
   * Get the path of the node for sharing purposes.
   */
  public function getSocialShareUrl(NodeInterface $node): string {
    return $this->getEntityUrlWithoutPrefix((int) $node->id());
  }

    /**
     * @throws InvalidArgumentException
     */
  private function getEntityUrlWithoutPrefix(int $entityId): string {
      return $this->request->getSchemeAndHttpHost() . $this->aliasManager->getAliasByPath('/node/' . $entityId);
  }
}
