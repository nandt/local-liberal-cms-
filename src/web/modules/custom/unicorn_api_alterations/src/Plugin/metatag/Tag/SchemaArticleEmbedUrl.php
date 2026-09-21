<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_article_embed_url",
 *   label = @Translation("embedUrl"),
 *   description = @Translation("Iframe embed URL for the video."),
 *   name = "embedUrl",
 *   group = "schema_article",
 *   weight = 15,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaArticleEmbedUrl extends SchemaNameBase {

}
