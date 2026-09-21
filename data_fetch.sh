#!/bin/bash

CONTAINER=$1

docker exec "$CONTAINER" drush gd-sync
docker exec "$CONTAINER" drush ineq-sync
