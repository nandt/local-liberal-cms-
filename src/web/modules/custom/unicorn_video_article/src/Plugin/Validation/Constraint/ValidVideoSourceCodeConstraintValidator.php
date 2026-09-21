<?php

declare(strict_types=1);

namespace Drupal\unicorn_video_article\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\unicorn_video_article\Support\HtmlFragmentValidator;
use Drupal\unicorn_video_article\Support\VideoArticleSupport;
use Masterminds\HTML5;
use Masterminds\HTML5\Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ValidVideoSourceCodeConstraintValidator extends ConstraintValidator {

  public function __construct(
    private readonly HtmlFragmentValidator $htmlFragmentValidator,
    private readonly VideoArticleSupport $videoArticleSupport,
  ) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof ValidVideoSourceCodeConstraint || !$value instanceof FieldItemListInterface) {
      return;
    }

    $node = $value->getEntity();
    if (!$node instanceof NodeInterface || !$this->videoArticleSupport->isVideoArticle($node)) {
      return;
    }

    $sourceCode = $value->value;
    if (!is_string($sourceCode) || trim($sourceCode) === '') {
      return;
    }

    if (!$this->isValidVideoSourceCode($sourceCode)) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }

  private function isValidVideoSourceCode(string $sourceCode): bool {
    if (!$this->htmlFragmentValidator->isValid($sourceCode)) {
      return FALSE;
    }

    try {
      $html = new HTML5(['disable_html_ns' => TRUE]);
      $fragment = $html->loadHTMLFragment($sourceCode);
    }
    catch (Exception) {
      return FALSE;
    }

    if ($html->hasErrors()) {
      return FALSE;
    }

    $iframeSources = $this->getIframeSources($fragment);
    if ($iframeSources === []) {
      return FALSE;
    }

    return array_all($iframeSources, fn (string $source): bool => $this->isValidUrl($source));
  }

  /**
   * @return string[]
   */
  private function getIframeSources(\DOMNode $node): array {
    $sources = [];

    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement && strtolower($child->tagName) === 'iframe') {
        $sources[] = trim($child->getAttribute('src'));
      }

      $sources = [...$sources, ...$this->getIframeSources($child)];
    }

    return $sources;
  }

  private function isValidUrl(string $url): bool {
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      return FALSE;
    }

    $parts = parse_url($url);

    return is_array($parts)
      && isset($parts['scheme'], $parts['host'])
      && in_array(strtolower($parts['scheme']), ['http', 'https'], TRUE)
      && $parts['host'] !== '';
  }

}
