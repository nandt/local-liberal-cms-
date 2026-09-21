FROM node:24.16.0-bookworm-slim
COPY ./src/web/themes/custom/ /liberal-cms/src/web/themes/custom

RUN cd /liberal-cms/liberal_theme/xaa_react_app; \
    npm install; \
    npm run prod; \
    rm -rf /liberal-cms/liberal_theme/xaa_react_app; \
    cd /liberal-cms/liberal_theme; \
    npm install -g sass; \
    npm install; \
    npm run build; \
    rm -rf /liberal-cms/liberal_theme/css/sass

RUN chown -R www-data:www-data /liberal-cms/src/web/themes
