<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_body",
 *   label = @Translation("articleBody"),
 *   description = @Translation("The full body text of the article."),
 *   name = "articleBody",
 *   group = "schema_article",
 *   weight = 12,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleBody extends SchemaNameBase {

}
