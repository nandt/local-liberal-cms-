#!/bin/bash

# Deletes everything that should not exist after a rebuild

THIS=$(dirname "$0")

rm -f $THIS/map.content.json
