#!/bin/bash

CONTAINER=$1
BASE_URL=$2

docker exec "$CONTAINER" drush --uri="$BASE_URL" cleanup:news