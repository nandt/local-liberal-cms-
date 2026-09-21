#!/usr/bin/env bash
#
# Build and push the liberal-cms image.
#
# pipefail matters here as much as -e: the DockerHub login is a pipeline
# (`echo password | docker login`), and without it a failed login is masked by
# echo's success and the build proceeds to fail later for an unrelated-looking
# reason.

set -eo pipefail

if [ -z "$1" ]
then
    echo "Error: please provide image target, e.g. dev or prod"
    echo "Dev image contains xdebug etc."
    # `exit` with no status returns the status of the LAST command — an echo,
    # which succeeded. That made every missing-argument path exit 0, so a build
    # that never ran reported success and the pipeline went green on a stale
    # image. Always exit non-zero here.
    exit 1
fi
TARGET="$1"

if [ -z "$2" ]
then
    echo "Error: please provide image tag"
    exit 1
fi
TAGS=$(echo "$2" | tr "," "\n")
TAG_PARAM=""
for TAG in $TAGS
do
  TAG_PARAM="$TAG_PARAM -t unicorndomaingr/liberal-cms:$TAG"
  TAG_CACHE=$TAG
done

if [ -z "$3" ]; then
    echo "Error: please provide the image tag of liberal-stocks-app-web"
    exit 1
fi

if [ -z "$4" ]; then
    echo "Error: please provide the image tag of liberal-dashboard-app-web"
    exit 1
fi

echo "Connecting to DockerHub"
echo "$DOCKER_HUB_PASSWORD" | docker login --username "$DOCKER_HUB_USER" --password-stdin

echo "Building docker image"
# shellcheck disable=SC2086
#docker build --cache-from unicorndomaingr/liberal-cms:"$TAG_CACHE" --build-arg BUILDKIT_INLINE_CACHE=1 $TAG_PARAM --target "$TARGET" .
docker build --pull --rm \
        --build-arg STOCKS_APP_WEB_TAG=$(echo "$3" | tr "," "\n") \
        --build-arg DASHBOARD_APP_WEB_TAG=$(echo "$4" | tr "," "\n") \
        $TAG_PARAM --target "$TARGET" .

echo "pushing to DockerHub"
for TAG in $TAGS
do
  docker push unicorndomaingr/liberal-cms:"$TAG"
done
