#!/bin/bash
set -e
exec docker-php-entrypoint apache2-foreground
