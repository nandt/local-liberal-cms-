#!/bin/bash

CONTAINER=$1

docker exec "$CONTAINER" drush cron
