<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Support;

use Masterminds\HTML5\Elements;
use Masterminds\HTML5\Exception;
use Masterminds\HTML5\Parser\EventHandler;
use Masterminds\HTML5\Parser\Scanner;
use Masterminds\HTML5\Parser\Tokenizer;

final class HtmlFragmentValidator implements EventHandler {

  /**
   * @var string[]
   */
  private array $openElements = [];

  private bool $valid = TRUE;

  public function isValid(string $fragment): bool {
    $this->openElements = [];
    $this->valid = TRUE;

    try {
      $scanner = new Scanner($fragment);
      if ($scanner->errors !== []) {
        return FALSE;
      }

      $tokenizer = new Tokenizer($scanner, $this);
      $tokenizer->parse();
    }
    catch (Exception) {
      return FALSE;
    }

    return $this->validationResult();
  }

  public function doctype($name, $idType = 0, $id = NULL, $quirks = FALSE): void {}

  /**
   * @param string $name
   * @param array<string, string> $attributes
   */
  public function startTag($name, $attributes = [], $selfClosing = FALSE): int {
    $elementType = Elements::element($name);

    if (!$selfClosing && ($elementType & Elements::VOID_TAG) === 0) {
      $this->openElements[] = $name;
    }

    return $elementType & (Elements::TEXT_RAW | Elements::TEXT_RCDATA);
  }

  /**
   * @param string $name
   */
  public function endTag($name): void {
    if (array_pop($this->openElements) !== $name) {
      $this->valid = FALSE;
    }
  }

  /**
   * @param string $cdata
   */
  public function comment($cdata): void {}

  /**
   * @param string $cdata
   */
  public function text($cdata): void {}

  public function eof(): void {
    if ($this->openElements !== []) {
      $this->valid = FALSE;
    }
  }

  /**
   * @param string $msg
   * @param int $line
   * @param int $col
   */
  public function parseError($msg, $line, $col): void {
    $this->valid = FALSE;
  }

  public function cdata($data): void {}

  public function processingInstruction($name, $data = NULL): void {}

  private function validationResult(): bool {
    return $this->valid && $this->openElements === [];
  }

}
