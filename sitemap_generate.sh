#!/bin/bash

CONTAINER="liberal-cms-drupal"

docker exec "$CONTAINER" drush sitemap:generate news
docker exec "$CONTAINER" drush sitemap:generate articles --date_option="month-current"
docker exec "$CONTAINER" drush sitemap:generate articles --date_option="month-previous"
docker exec "$CONTAINER" drush sitemap:generate podcasts --date_option="year-current"
docker exec "$CONTAINER" drush sitemap:generate index
