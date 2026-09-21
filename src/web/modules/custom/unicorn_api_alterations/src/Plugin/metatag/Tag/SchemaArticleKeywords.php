<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_keywords",
 *   label = @Translation("keywords"),
 *   description = @Translation("Use the tags assigned to the article."),
 *   name = "keywords",
 *   group = "schema_article",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleKeywords extends SchemaNameBase {

}
