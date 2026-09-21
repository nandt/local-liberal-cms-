<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_duration",
 *   label = @Translation("duration"),
 *   description = @Translation("ISO 8601 duration of the video."),
 *   name = "duration",
 *   group = "schema_article",
 *   weight = 13,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleDuration extends SchemaNameBase {

}
