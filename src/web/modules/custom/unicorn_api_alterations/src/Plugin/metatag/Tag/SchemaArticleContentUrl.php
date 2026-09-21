<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_content_url",
 *   label = @Translation("contentUrl"),
 *   description = @Translation("Direct video stream URL."),
 *   name = "contentUrl",
 *   group = "schema_article",
 *   weight = 14,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleContentUrl extends SchemaNameBase {

}
