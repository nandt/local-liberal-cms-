# Description

Liberal-cms is a Drupal based content management system and provides all the necessary functionality to host the Liberal newspaper

## Deployment

First you have to clone the project from the github/bitbucket repo. Then using command line (terminal for macOS, Git Bash here option, Cywin for win10) you have to navigate into the root folder of the project. You will find a bash script ./run.sh

```bash
./run.sh
```

The first time, you should select option 7 to download the latest database snapshot from develop.unicorndomain.gr. Then select one of the choices `1` or `2` to start the development or production image respectively. On the first run the database will be populated and this will take a few minutes, therefore ignore any errors on the browser and try again in a while. On subsequent runs, the database will be loaded from the volume, so it will be much faster.

### Overriding confguration

Configuration of the deployment takes place through environment variables. If you need to override any, copy `.env.example` to `.env` and edit them.

### Run commands inside docker

```bash
docker exec -it liberal-cms-drupal /bin/bash
```

After your connection inside docker try to find a salt.txt file and get from there the hash_salt and place it on your .env file

### Troubleshooting - Rebuild cache

In case you get errors after the DB has been created, you probably need to clear the cache from the database. So, get a shell to the drupal container as above and

```bash
./vendor/drush/drush/drush cr
```

For convenience you can use option `5` of the `run.sh` script.

### Recreate database

The database is populated the first time you run the deployment, stored in a docker volume and reused on each subsequent run. To clear the database and recreate it (for example, when you download a new snapshot from the development server), you need to remove the volume. So, with the deployment terminated:

```bash
docker volume rm liberal-cms_liberal
```

For convenience you can use options `6` of the `run.sh` script.

The proceed with options 1 or 2 and the new DB will be loaded

## React Installation

Navigate to

```
/src/web/modules/custom/liberal_dashboard_app/react_app
```

Install the node packages

```
npm install
```

### 1. Local development flow

- Copy `.env.local.example` to `.env.local` and fill the `APP_URL` env variable accordingly (E.g. http://$DRUPAL_TRUSTED_HOST:$DRUPAL_PORT)
- Build the development app (also watches for changes in codebase)

```
npm run start
```

### 2. Deploy staging server

```
npm run dev
or
npm run develop
or
npm run development
```

### 3. Deploy live server

```
npm run build
or
npm run prod
or
npm run production
```

## TinyMCE local development API KEY (if you dont want to publish one for yourself)

```
go17ops5o6n1o9zr2ok4g61r9bmxkwrmatyzte0he0436waw
```

## Tips for development

After pulling from develop, it is good enough to clear cache tables from db_schema, in case you receive a message `The website encountered an unexpected error. Please try again later.`. Use a db viewer like workbench to empty all tables that contains the wording `cache`.
All composer require modules must be done on develop branch. Do not require a module on a personal branch usually after merging it, you will face conflicts
All db_schemas must be cleared from cached data. Every time a new module is installed through composer, then each user has to enable it or passing the newly db_schema.
In order to login into admin panel please use the following url /user/login (ex. http://liberal.test/en/user/login).

## Add new file-types on Drupal

In case you would like to upload a file (image, doc, pdf, csv), you have first to declare it on allowed file type (admin/structure/file-types). Click add new provide file's mime-type and save it.

### Kubernetes deployment
Go to /helm
Execute helmfile -i apply

version: 0.0.6 (30/08/2024)

Dummy: YP
