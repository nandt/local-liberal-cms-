<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\unicorn_api_alterations\Controller\SectionTemplatesController;
use Drupal\unicorn_api_alterations\Entity\Section;
use Masterminds\HTML5;
use Masterminds\HTML5\Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ValidAdvertisingSectionImageSnippetConstraintValidator extends ConstraintValidator {

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof ValidAdvertisingSectionImageSnippetConstraint || !$value instanceof FieldItemListInterface) {
      return;
    }

    $section = $value->getEntity();
    if (!$section instanceof Section || !$this->isAdvertisingSection($section)) {
      return;
    }

    $snippet = $value->value;
    if (!is_string($snippet) || trim($snippet) === '') {
      return;
    }

    if (!$this->isValidSnippet($snippet)) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }

  private function isAdvertisingSection(Section $section): bool {
    $templateId = $section->get('template')->value;

    foreach (SectionTemplatesController::TEMPLATES as $template) {
      if ($template['id'] === $templateId) {
        return !empty($template['isAd']);
      }
    }

    return FALSE;
  }

  private function isValidSnippet(string $snippet): bool {
    try {
      $html = new HTML5(['disable_html_ns' => TRUE]);
      $fragment = $html->loadHTMLFragment($snippet);
    }
    catch (Exception) {
      return FALSE;
    }

    if ($html->hasErrors()) {
      return FALSE;
    }

    $imageSources = $this->getImageSources($fragment);
    if ($imageSources === []) {
      return FALSE;
    }

    return array_all($imageSources, fn (string $source): bool => $this->isValidUrl($source));
  }

  /**
   * @return string[]
   */
  private function getImageSources(\DOMNode $node): array {
    $sources = [];

    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement && strtolower($child->tagName) === 'img') {
        $sources[] = trim($child->getAttribute('src'));
      }

      $sources = [...$sources, ...$this->getImageSources($child)];
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
