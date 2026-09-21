<?php

namespace Drupal\unicorn_api_alterations\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * @MetatagTag(
 *   id = "article_publisher",
 *   label = @Translation("article:publisher"),
 *   description = @Translation("The publisher's Facebook page URL."),
 *   name = "article:publisher",
 *   group = "advanced",
 *   weight = 4,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class ArticlePublisher extends MetaPropertyBase {

}
