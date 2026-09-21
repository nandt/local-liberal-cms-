#!/bin/bash
set -e
MARIADB_CONTAINER="${MARIADB_CONTAINER:-liberal-cms-mariadb}"
MARIADB_USER="${MARIADB_USER:-liberal}"
MARIADB_PASSWORD="${MARIADB_PASSWORD:-liberal}"
MARIADB_DATABASE="${MARIADB_DATABASE:-liberal}"
SNAPSHOTS_TO_KEEP=11
SCRIPT_DIR=$(cd "$(dirname "$0")" || exit; pwd)
FTP_HOST=ftp01.vps.top.host
FTP_USER=kvm2342
FTP_PASSWORD="8z)#3%?A"

# Load the lftp command
source "$(dirname "$0")/lftp.sh"

# Get the backup from MariaDB
echo "Creating database snapshot"
mkdir -p "$SCRIPT_DIR/backup.liberal.gr"
DATETIME=$(date +%Y_%m_%d_%H_%M_%S)
docker exec -i "$MARIADB_CONTAINER" mysqldump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --databases "$MARIADB_DATABASE" --skip-comments > "$(dirname "$0")/backup.liberal.gr/liberal_drupal_dev_$DATETIME.sql"

# Fix permissions
chgrp unicorndev "$(dirname "$0")/backup.liberal.gr/liberal_drupal_dev_$DATETIME.sql"

# Keep last 10 snapshots (+ the default name)
echo "Removing old snapshots"
(cd "$(dirname "$0")/backup.liberal.gr/" && ls -tp | grep -v '/$' | tail -n +"$SNAPSHOTS_TO_KEEP" | xargs -I {} rm -- {})

# Replace the default name with the last snapshot
echo "Replacing the default snapshot"
cp "$(dirname "$0")/backup.liberal.gr/liberal_drupal_dev_$DATETIME.sql" "$(dirname "$0")/backup.liberal.gr/liberal_drupal_dev.sql"

# Sync snapshots to FTP server
echo "Syncing snapshots to FTP server"
lftp "mkdir -p -f backup.liberal.gr/; mirror -R backup.liberal.gr/ backup.liberal.gr/"