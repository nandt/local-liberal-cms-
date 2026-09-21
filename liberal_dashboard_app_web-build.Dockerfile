FROM node:24.16.0-bookworm-slim
COPY ./src/web/modules/custom/ /liberal-cms/src/web/modules/custom

ARG DEPLOYMENT
RUN cd /liberal-cms/src/web/modules/custom/liberal_dashboard_app/react_app; \
    npm install; \
    npm run $DEPLOYMENT;

RUN chown -R www-data:www-data /liberal-cms/src/web/modules
