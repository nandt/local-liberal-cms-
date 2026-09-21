DOCKER_REGISTRY=unicorndomaingr

build_dashboard_app_web_dockerhub()
{
  echo "Input image target, .e.g dev or prod:"
  echo "Dev image contains xdebug etc."
  # shellcheck disable=SC2162
  read TARGET

  echo "Input image tag:"
  # shellcheck disable=SC2162
  read TAG
  echo "Connecting to DockerHub"
  sudo docker login
  echo "Building docker image"
  docker build --build-arg DEPLOYMENT="$TARGET" -t $DOCKER_REGISTRY/liberal-dashboard-app-web:"$TAG" -f ./liberal_dashboard_app_web-build.Dockerfile .
  echo "Pushing image to DockerHub"
  sudo docker push $DOCKER_REGISTRY/liberal-dashboard-app-web:"$TAG"
}

build_dashboard_app_web_locally()
{
  echo "Input image target, .e.g dev or prod:"
  echo "Dev image contains xdebug etc."
  # shellcheck disable=SC2162
  read TARGET

  echo "Input image tag:"
  # shellcheck disable=SC2162
  read TAG
  docker build --build-arg DEPLOYMENT="$TARGET" -t liberal-dashboard-app-web:"$TAG" -f ./liberal_dashboard_app_web-build.Dockerfile .
}

build_stocks_app_web_dockerhub()
{
  echo "Input image tag:"
  # shellcheck disable=SC2162
  read TAG
  echo "Connecting to DockerHub"
  sudo docker login
  echo "Building docker image"
  docker build -t $DOCKER_REGISTRY/liberal-stocks-app-web:"$TAG" -f ./liberal_stocks_app_web-build.Dockerfile .
  echo "Pushing image to DockerHub"
  sudo docker push $DOCKER_REGISTRY/liberal-stocks-app-web:"$TAG"
}

build_stocks_app_web_locally()
{
  echo "Input image tag:"
  # shellcheck disable=SC2162
  read TAG
  echo "Building docker image"
  docker build -t liberal-stocks-app-web:"$TAG" -f ./liberal_stocks_app_web-build.Dockerfile .
}

echo_image_menu()
{
  echo "Choose image to build:"
  echo "(1) liberal-dashboard-app-web"
  echo "(2) liberal-stocks-app-web"
  # shellcheck disable=SC2162
  read IMAGE
}

echo "Choose build target:"
echo "(1) local"
echo "(2) DockerHub"
# shellcheck disable=SC2162
read BUILD_TARGET

if [ "$BUILD_TARGET" -eq 1 ]; then
  echo_image_menu
  if [ "$IMAGE" -eq 1 ]; then
    build_dashboard_app_web_locally
  elif [ "$IMAGE" -eq 2 ]; then
    build_stocks_app_web_locally
  else
    echo "Invalid option"
  fi
elif [ "$BUILD_TARGET" -eq 2 ]; then
  echo_image_menu
  if [ "$IMAGE" -eq 1 ]; then
    build_dashboard_app_web_dockerhub
  elif [ "$IMAGE" -eq 2 ]; then
    build_stocks_app_web_dockerhub
  else
    echo "Invalid option"
  fi
else
  echo "Invalid option"
fi