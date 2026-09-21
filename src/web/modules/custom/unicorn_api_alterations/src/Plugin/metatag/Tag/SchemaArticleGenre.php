<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_genre",
 *   label = @Translation("genre"),
 *   description = @Translation("Category of the video."),
 *   name = "genre",
 *   group = "schema_article",
 *   weight = 16,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleGenre extends SchemaNameBase {

}
