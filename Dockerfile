FROM drupal:11.4.6-php8.4-apache-bookworm AS base
RUN a2enmod ssl
RUN a2enmod proxy_http
ADD https://github.com/phpredis/phpredis/archive/refs/tags/6.2.0.tar.gz /tmp/redis.tar.gz
ADD https://github.com/wikimedia/excimer/archive/refs/heads/master.tar.gz /tmp/excimer.tar.gz
ADD https://github.com/krakjoe/apcu/archive/refs/tags/v5.1.28.tar.gz /tmp/apcu.tar.gz
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends autoconf gcc make libc6-dev pkg-config; \
    for e in redis excimer apcu; do \
      mkdir -p /tmp/src-$e; tar -xzf /tmp/$e.tar.gz -C /tmp/src-$e --strip-components=1; \
      cd /tmp/src-$e; phpize; ./configure; make -j"$(nproc)"; make install; \
      docker-php-ext-enable $e; \
    done; \
    apt-get purge -y autoconf gcc make libc6-dev pkg-config; \
    rm -rf /tmp/src-* /tmp/*.tar.gz /var/lib/apt/lists/*
RUN apt-get update && apt-get install -y --no-install-recommends git default-mysql-client && rm -rf /var/lib/apt/lists/*
COPY ./docker/app/patches/apache.patch /etc/apache2/sites-available/apache.patch
RUN patch --ignore-whitespace -d /etc/apache2/sites-available/ -i /etc/apache2/sites-available/apache.patch
COPY ./docker/app/apache/header-length.conf /etc/apache2/conf-enabled/header-length.conf
COPY ./docker/app/php/expose_off.ini $PHP_INI_DIR/conf.d/
COPY ./docker/app/drupal_settings/settings.php web/sites/default/settings.php
COPY ./docker/app/drupal_settings/cors.services.yml web/sites/default/cors.services.yml
COPY src/composer.json .
COPY src/composer.lock .
COPY src/custom_packages ./custom_packages
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader
COPY ./docker/app/patches/klaro_custom.patch .
RUN patch -d /opt/drupal/web/modules/contrib/klaro -p1 -i /opt/drupal/klaro_custom.patch
COPY ./docker/app/apache/.htaccess /opt/drupal/web/.htaccess
COPY ./docker/app/apache/ports.conf /etc/apache2/ports.conf
RUN mkdir -p config/sync
COPY src/config/sync config/sync
COPY src/web/googled79d7717f8d23830.html /opt/drupal/web/googled79d7717f8d23830.html
RUN rm /opt/drupal/web/robots.txt
RUN mkdir twig_cache
COPY ./src/cli ./cli
RUN mkdir -p /opt/drupal/web/sites/default/files && \
    chown -R www-data:www-data /opt/drupal/web/sites/default/files

# Everything both targets need. Ό,τι μπει μόνο στο dev λείπει από staging/prod —
# έτσι χάθηκε το prod stage στο a678dbe2 και το pipeline χτίζει --target prod από τότε.
FROM base AS common
# Serve on BOTH "/" and /$APP_PREFIX. /var/www/html is a wrapper dir holding a
# symlink $APP_PREFIX -> /opt/drupal/web; DocumentRoot is that symlink, and
# app-prefix.conf aliases /$APP_PREFIX onto the same directory. So a request keeps
# whichever base it arrived on, and Drupal derives its base path from SCRIPT_NAME:
# "/" for /<section>/<slug>?amp (the AMP variant readers get), /$APP_PREFIX/ for
# everything the frontend and the ALB rule address.
#
# DocumentRoot deliberately sits on the symlink and NOT on /var/www/html, which is
# also where the chart mounts the simple_oauth signing keys. One level up and
# /oauth-keys/private.key is a plain readable file under the docroot.
#
# There is no RewriteBase any more — one base cannot describe both — so .htaccess
# captures the prefix out of REQUEST_URI and substitutes an absolute URL-path.
#
# The prefix is a build arg because environments disagree: develop serves /liberal,
# staging serves /liberal-cms (it must match the ALB listener rule, the helm chart's
# headless.basePath and the frontend's baked NEXT_PUBLIC_API_URL). Hardcoding it here
# is what left the staging image serving /liberal while everything else asked for
# /liberal-cms, so every request 404'd.
#
# .htaccess_dev carries __APP_PREFIX__ placeholders that are substituted below. The
# substitution is deliberately on the placeholder and NOT on the literal "liberal",
# because that string also appears in module paths (liberal_advertising_tools) which
# must not be rewritten. The default keeps develop's image byte-identical.
ARG APP_PREFIX=liberal
COPY ./docker/app/apache/.htaccess_dev /opt/drupal/web/.htaccess
RUN test -n "$APP_PREFIX" && sed -i "s|__APP_PREFIX__|${APP_PREFIX}|g" /opt/drupal/web/.htaccess
RUN rm -f /var/www/html && mkdir -p /var/www/html && ln -sfn /opt/drupal/web /var/www/html/${APP_PREFIX}
COPY ./docker/app/apache/app-prefix.conf /etc/apache2/conf-enabled/app-prefix.conf
RUN sed -i "s|__APP_PREFIX__|${APP_PREFIX}|g" /etc/apache2/conf-enabled/app-prefix.conf && \
    sed -i "s|DocumentRoot /var/www/html$|DocumentRoot /var/www/html/${APP_PREFIX}|" /etc/apache2/sites-available/000-default.conf && \
    grep -q "DocumentRoot /var/www/html/${APP_PREFIX}$" /etc/apache2/sites-available/000-default.conf
COPY ./src/web/modules/custom /opt/drupal/web/modules/custom
COPY ./src/web/themes/custom /opt/drupal/web/themes/custom
RUN mkdir -p /opt/drupal/web/modules/custom/liberal_advertising_tools/dist \
    && touch /opt/drupal/web/modules/custom/liberal_advertising_tools/dist/main.bundle.min.js \
    && touch /opt/drupal/web/modules/custom/liberal_advertising_tools/dist/main.bundle.min.css
RUN mkdir -p web/libraries && chown -R www-data:www-data web/sites web/modules web/themes web/libraries config/sync twig_cache

COPY ./docker/app/entrypoint.sh /usr/local/bin/liberal-entrypoint.sh
COPY ./docker/app/migrate.sh /usr/local/bin/liberal-migrate.sh
COPY ./docker/app/verify.sh /usr/local/bin/liberal-verify.sh
COPY ./docker/app/finalize.sh /usr/local/bin/liberal-finalize.sh
COPY ./docker/app/transition.sh /usr/local/bin/liberal-transition.sh
RUN chmod +x /usr/local/bin/liberal-entrypoint.sh /usr/local/bin/liberal-migrate.sh /usr/local/bin/liberal-verify.sh /usr/local/bin/liberal-finalize.sh /usr/local/bin/liberal-transition.sh
ENTRYPOINT ["/usr/local/bin/liberal-entrypoint.sh"]

# staging + production: php.ini-production (display_errors off), χωρίς xdebug και
# χωρίς settings.local.php — αυτό είναι που κάρφωνε error_level σε verbose.
FROM common AS prod
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY ./docker/app/php/memory_limit.prod.ini $PHP_INI_DIR/conf.d/memory_limit.ini
COPY ./docker/app/php/uploads.prod.ini $PHP_INI_DIR/conf.d/uploads.ini

FROM common AS dev
RUN cp $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
# Dev-only contrib (openapi_jsonapi, openapi_ui_swagger, schemata) live in
# require-dev; the base stage installed with --no-dev, so pull them into the
# dev image here. Prod/staging stay lean.
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-ansi --no-interaction --no-progress --no-scripts --optimize-autoloader
COPY ./docker/app/php/memory_limit.dev.ini $PHP_INI_DIR/conf.d/memory_limit.ini
COPY ./docker/app/php/uploads.dev.ini $PHP_INI_DIR/conf.d/uploads.ini
COPY ./docker/app/drupal_settings/settings.local.php web/sites/default/settings.local.php
COPY ./docker/app/drupal_settings/development.services.yml web/sites/development.services.yml
ADD https://github.com/xdebug/xdebug/archive/refs/tags/3.4.1.tar.gz /tmp/xdebug.tar.gz
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends autoconf gcc make libc6-dev; \
    mkdir -p /tmp/src-xdebug; tar -xzf /tmp/xdebug.tar.gz -C /tmp/src-xdebug --strip-components=1; \
    cd /tmp/src-xdebug; phpize; ./configure --enable-xdebug; make -j"$(nproc)"; make install; \
    docker-php-ext-enable xdebug; \
    apt-get purge -y autoconf gcc make libc6-dev; \
    rm -rf /tmp/src-* /tmp/*.tar.gz /var/lib/apt/lists/*
COPY ./docker/app/php/docker-php-ext-xdebug.ini $PHP_INI_DIR/conf.d/
# Swagger UI: μόνο εδώ. Το migrate.sh ενεργοποιεί τα openapi modules μόνο σε
# local/dev/develop, οπότε σε staging/prod δεν πρέπει να υπάρχουν ούτε τα assets.
COPY ./docker/app/swagger-ui /opt/drupal/web/libraries/swagger-ui/dist
RUN chown -R www-data:www-data web/sites web/libraries
