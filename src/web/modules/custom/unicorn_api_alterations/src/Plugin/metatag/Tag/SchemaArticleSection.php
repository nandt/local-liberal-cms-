<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_section",
 *   label = @Translation("articleSection"),
 *   description = @Translation("Use the category assigned to the article."),
 *   name = "articleSection",
 *   group = "schema_article",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleSection extends SchemaNameBase {

}
