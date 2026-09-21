<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VideoArticleDto {

  /**
   * @param array<string, mixed> $meta
   * @param array<string, mixed> $input
   */
  public function __construct(
    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 64)]
    public string $uid,

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?float $duration,

    #[Assert\NotBlank]
    #[Assert\Url]
    public ?string $thumbnail,

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $width,

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $height,

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $size,

    #[Assert\NotBlank]
    public ?string $uploaded,

    public array $meta = [],
    public array $input = [],
  ) {}

  /**
   * @param array<string, mixed> $data
   */
  public static function fromApiResponse(array $data): self {
    return new self(
      uid: $data['uid'],
      duration: isset($data['duration']) ? (float) $data['duration'] : null,
      thumbnail: $data['thumbnail'] ?? null,
      width: isset($data['input']['width']) ? (int) $data['input']['width'] : null,
      height: isset($data['input']['height']) ? (int) $data['input']['height'] : null,
      size: isset($data['size']) ? (int) $data['size'] : null,
      uploaded: $data['uploaded'] ?? null,
      meta: $data['meta'] ?? [],
      input: $data['input'] ?? [],
    );
  }

}
