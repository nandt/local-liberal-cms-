<?php

declare(strict_types=1);

namespace Drupal\liberal_stocks_app\Resources;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\liberal_stocks_app\Utils\LiberalStocksAppUtils;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\unicorn_core\Resources\BaseResource;

class StocksAppResource extends BaseResource
{

  /**
   * @param object{
   *   nid: string,
   *   title: string,
   *   name: string,
   *   created: string,
   *   field_stc_symbol_en_value: string,
   *   field_teleytaia_enimerosi_value: ?string,
   * } $node
   *
   * @return array{
   *   nid: string,
   *   title: string,
   *   view_node: string,
   *   field_stc_symbol_en: string,
   *   created: string,
   *   field_teleytaia_enimerosi_value: string,
   *   field_liberal_category: string,
   * }
   */

  // TODO: find better name when revisit and analysis ready
  public function toBackendCall(object $node): array
  {
    $stc_symbol_en = Xss::filterAdmin($node->field_stc_symbol_en_value);
    $title = Xss::filterAdmin($node->title);
    $liberal_category = Xss::filterAdmin($node->name);
    $published_date_formatted = LiberalStocksAppUtils::getFormattedDate($node->created);

    if (!empty($node->field_teleytaia_enimerosi_value)) {
      $te_formatted = LiberalStocksAppUtils::getFormattedDate($node->field_teleytaia_enimerosi_value, false);
    }
    else {
      // TODO don't assign created to enimerosi when app is fixed. We can have empty value here.
      $te_formatted = $published_date_formatted;
    }

    return [
      'nid' => $node->nid,
      'title' => $title,
      'view_node' => Url::fromRoute('entity.node.canonical', ['node' => $node->nid])->toString(TRUE)->getGeneratedUrl(),
      'field_stc_symbol_en' => $stc_symbol_en,
      'created' => $published_date_formatted['value'],
      'field_teleytaia_enimerosi_value' => $te_formatted['value'] ?? "",
      'field_liberal_category' => $liberal_category ?? "",
    ];
  }

  /**
   * @param array<int, TermInterface> $terms
   *
   * @return array{
   *   nid: string,
   *   title: string,
   *   created: string,
   *   field_teleytaia_enimerosi: string,
   *   uuid: string,
   *   body: string,
   *   field_subtitle: string,
   *   view_node: string,
   *   field_emfanisi_arthrografoy_stin: string,
   *   field_arthrografos: string,
   *   field_liberal_category: string,
   *   field_arthrografos_1: string,
   *   field_liberal_category_1: string,
   * }
   */
  // TODO: find better name when revisit and analysis ready
  public function toFrontendCall(NodeInterface $node, array $terms): array
  {
    $author_name = null;
    $author_url = null;
    $category_name = null;
    $category_url = null;

    $author_id = $node->get('field_arthrografos')->target_id;

    if (isset($terms[$author_id])) {
      $author = $terms[$author_id];

      if ($author instanceof TermInterface) {
        $author_name = Html::escape((string) $author->label());
        $author_url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $author->id()])->toString(TRUE)->getGeneratedUrl();
      }
    }

    $category_id = $node->get('field_liberal_category')->target_id;

    if (isset($terms[$category_id])) {
      $category = $terms[$category_id];

      if ($category instanceof TermInterface) {
        $category_name = Html::escape((string) $category->label());
        $category_url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $category->id()])->toString(TRUE)->getGeneratedUrl();
      }
    }

    $node_title = Xss::filterAdmin((string) $node->getTitle());

    // view_node
    $view_node = Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(TRUE)->getGeneratedUrl();

    $published_date_formatted = LiberalStocksAppUtils::getFormattedDate($node->getCreatedTime());

    if (!empty($node->get('field_teleytaia_enimerosi')->value)) {
      $te_formatted = LiberalStocksAppUtils::getFormattedDate($node->get('field_teleytaia_enimerosi')->value, false);
    }
    else {
      // TODO don't assign created to enimerosi when app is fixed. We can have empty value here.
      $te_formatted = $published_date_formatted;
    }

    // get summary or trim to desired character length
    $body_ref = $node->get('body');
    $summary = $this->getBodyTrimmedOrSummary($body_ref, 400);

    // Subtitle
    $subtitle = !empty($node->get('field_subtitle')->value) ? strip_tags((string) $node->get('field_subtitle')->value) : "";

    return [
      'nid' => (string) $node->id(),
      'title' => $node_title,
      'created' => $published_date_formatted['value'],
      'field_teleytaia_enimerosi' => $te_formatted['value'] ?? "",
      'uuid' => (string) $node->uuid(),
      'body' => $summary,
      'field_subtitle' => $subtitle,
      'view_node' => $view_node,
      'field_emfanisi_arthrografoy_stin' => "",
      'field_arthrografos' => $author_name ?? "",
      'field_liberal_category' => $category_name ?? "",
      'field_arthrografos_1' => $author_url ?? "",
      'field_liberal_category_1' => $category_url ?? "",
    ];
  }

  /**
   * @param FieldItemListInterface<FieldItemInterface> $body_ref
   */
  private function getBodyTrimmedOrSummary(FieldItemListInterface $body_ref, int $trim): string
  {
    $body_summary = $body_ref->summary;

    if (!empty($body_summary)) {
      $value = $body_summary;
    }
    else {
      // Get the trimmed version of the body text.
      $body_value = $body_ref->value;
      $value = text_summary($body_value, 'full_html', $trim);
    }

    // Strip HTML tags from the text.
    return strip_tags((string) $value);
  }
}
