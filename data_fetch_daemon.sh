#!/bin/bash

INTERVAL=$1
START_HOUR=$2
END_HOUR=$3

echo "Starting update loop"

while true
do
  HOUR="$(date +'%H')"
  if [ "$HOUR" -ge "$START_HOUR" ] && [ "$HOUR" -lt "$END_HOUR" ]; then
    echo "Updating..."
    drush gd-sync &
    drush ineq-sync &
    sleep "$INTERVAL"
  else
    echo "It is $(date), XAA should be closed, exiting"
    exit 0
  fi
done