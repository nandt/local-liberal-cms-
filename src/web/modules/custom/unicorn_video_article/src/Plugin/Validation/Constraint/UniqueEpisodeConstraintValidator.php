<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueEpisodeConstraintValidator extends ConstraintValidator {

  private readonly EntityStorageInterface $nodeStorage;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UniqueEpisodeConstraint || !$value instanceof FieldItemListInterface) {
      return;
    }

    $node = $value->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }

    $episode = $value->value;
    $seriesId = $node->get('field_vid_article_series')->target_id;

    if (!$episode || !$seriesId) {
      return;
    }

    $query = $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article_liberal')
      ->condition('field_vid_article_episode', $episode)
      ->condition('field_vid_article_series', $seriesId)
      ->count();

    if (!$node->isNew()) {
      $query->condition('nid', $node->id(), '<>');
    }

    $result = $query->execute();

    if ($result) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }

}
