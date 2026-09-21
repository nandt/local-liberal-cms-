<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_in_language",
 *   label = @Translation("inLanguage"),
 *   description = @Translation("Language of the content."),
 *   name = "inLanguage",
 *   group = "schema_article",
 *   weight = 17,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleInLanguage extends SchemaNameBase {

}
