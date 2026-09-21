#!/bin/bash

# Download images from the old server

THIS=$(dirname "$0")/../migration

cat $THIS/photo_url.tsv | xargs -I {} wget -N {} -P $THIS/photo
