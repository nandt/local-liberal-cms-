#! /bin/bash

CONTAINER=$1
BASE_URL=$2

docker exec "$CONTAINER" drush --uri="$BASE_URL" websub:ping
docker exec "$CONTAINER" drush --uri="$BASE_URL" sitemap:generate index_xrimatistirio
docker exec "$CONTAINER" drush --uri="$BASE_URL" sitemap:generate stocks
docker exec "$CONTAINER" drush --uri="$BASE_URL" sitemap:generate indices
docker exec "$CONTAINER" drush --uri="$BASE_URL" sitemap:generate news