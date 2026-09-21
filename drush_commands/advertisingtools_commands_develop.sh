#!/bin/bash

CONTAINER=$1
BASE_URL=$2

docker exec "$CONTAINER" drush --uri="$BASE_URL" advertisingtools:consume_adtracker

docker exec "$CONTAINER" drush --uri="$BASE_URL" advertisingtools:checkadscriteria

docker exec "$CONTAINER" drush --uri="$BASE_URL" advertisingtools:page_cache_invalidate

docker exec "$CONTAINER" drush --uri="$BASE_URL" advertisingtools:consume_statistics