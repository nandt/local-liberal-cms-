#!/bin/bash

BASE_URL="$1"

drush --uri="$BASE_URL" advertisingtools:consume_adtracker

drush --uri="$BASE_URL" advertisingtools:checkadscriteria