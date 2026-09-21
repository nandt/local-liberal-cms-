#!/bin/bash

CONTAINER=$1
BASE_URL=$2

docker exec "$CONTAINER" drush --uri="$BASE_URL" --date_option=\"month-previous\" sitemap:generate articles