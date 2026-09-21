#!/bin/bash

DEVELOPMENT_SERVER=develop.unicorndomain.gr
DEVELOPMENT_SERVER_DB_BACKUP_PATH=/home/liberal/backup.liberal.gr
SNAPSHOT_NAME=liberal_drupal_dev.sql

clear
echo -e "*****************************"
echo -e "*    Welcome to Liberal     *"
echo -e "*          Dev env          *"
echo -e "*  Please work with caution *"
echo -e "* By: CP v0.0.2             *"
echo -e "*****************************"
echo -e " "
echo -e "Please select : \n"
echo -e " (1) to deploy development env (xdebug + npm run dev)"
echo -e " (2) to deploy production env with dev server configuration (no debugger, url: /liberal)"
echo -e " (3) to start the npm development server for theme - after starting the development environment in option 1"
echo -e " (4) to deploy production env (no debugger + npm run prod)"
echo -e " (5) to terminate the deployment (stop and remove containers)"
echo -e " (6) to stop the containers"
echo -e " (7) to rebuild the cache"
echo -e " (8) to remove the DB volume"
echo -e " (9) to sync the db snapshots from develop.unicorndomain.gr"
read OPTION

docker network create docker_liberal-stocks-net

case $OPTION in

    1)
    DASHBOARD_APP_WEB_TAG=latest STOCKS_APP_WEB_TAG=latest DRUPAL_ENV=dev USERID=$(id -u) GROUPID=$(id -g) USERNAME=$(id -un) docker compose -f ./docker-compose.yml -f ./docker-compose.override.yml -f ./docker-compose.local-mount.yml up --remove-orphans --build --force-recreate --detach
    ;;

    2)
    DASHBOARD_APP_WEB_TAG=latest STOCKS_APP_WEB_TAG=latest DRUPAL_ENV=dev-server USERID=$(id -u) GROUPID=$(id -g) USERNAME=$(id -un) docker compose -f ./docker-compose.yml -f ./docker-compose.override.yml -f ./docker-compose.local-mount.yml up --remove-orphans --build --force-recreate --detach
    ;;

    3)
    cd ./src/web/themes/custom/liberal_theme/ || exit
    npm install
    npx gulp watch
    ;;

    4)
    DASHBOARD_APP_WEB_TAG=latest STOCKS_APP_WEB_TAG=latest DRUPAL_ENV=prod docker compose up --remove-orphans --build --force-recreate --detach
    ;;

    5)
    docker compose down
    ;;

    6)
    docker compose stop
    ;;

    7)
    docker exec liberal-cms-drupal drush cr
    ;;

    8)
    docker volume rm liberal-cms_liberal
    ;;

    9)
    SCRIPT_DIR=$(cd "$(dirname "$0")" || exit; pwd)
    echo "Provide the SSH username towards $DEVELOPMENT_SERVER"
    read SSH_USERNAME
    mkdir -p "$SCRIPT_DIR/backup.liberal.gr"
    docker run -it -e USER_ID="$(id -u)" -e GROUP_ID="$(id -g)" -v "$HOME/.ssh:/home/user/.ssh" -v "$SCRIPT_DIR/backup.liberal.gr:/backup.liberal.gr" minidocks/rsync -r "$SSH_USERNAME"@$DEVELOPMENT_SERVER:$DEVELOPMENT_SERVER_DB_BACKUP_PATH/$SNAPSHOT_NAME /backup.liberal.gr/$SNAPSHOT_NAME
    ;;

    *)
    echo -n "unknown choice"
    ;;
esac
