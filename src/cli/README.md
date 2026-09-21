# Final process

1. Migrate authors
1. Migrate tags
1. Fetch images
1. Download images
1. Upload images
1. Migrate nodes
1. Migrate liveMarkets
1. Store migrated id
1. Fix encoding
1. Fix duplicate authors
1. Fix nodes (with body images)
1. Fix timestamps


## How to update the DB

The DB is imported or exported through `liberal_drupal_dev.sql`

Export: In container: `vendor/bin/drush cex` and outside container: `bash db.export.sh`.

Import/Rebuild: Outside container: `bash run.sh`, `1`

## How to migrate from the old db

Images must be uploaded first because the migrated articles must link with them.
Then the articles must be transferred. The operation is idempotent because of the
generated and git tracked `cli/map.*.json` files. All commands are given inside the container.

1. Map all the image names of the old server in `cli/photo_url.tsv` while creating `cli/map.image_download.json` to avoid duplicates: `php cli/fetch-images.php`.
2. Download the images in `cli/photos/` by reading `photo_url.tsv`: `bash cli/download.sh`.
3. Read through the `cli/photos/` dir and upload the images to the amazon server while creating `cli/map.image_upload.json` to avoid duplicates: `php cli/upload.php`.
4. Transfer the articles while creating `cli/map.content.json` to avoid duplicates: `php cli/migration.php`.

After a rebuild or pipeline deploy the db is reset,
but some map files may exist from a previous operation,
now linking to non-existing db records. To remove these files: `bash cli/clear.sh`.

The script `cli/upload.php` costs because of the cloud server. A good idea is
to use it only once. However, the script also inserts records in the db
(tables: lib_file_managed, lib_s3fs_file), so if the plan is to use the script once,
then the db state must be exported into version control, or be stored somewhere.

## Troubleshooting

Before executing a cli script, a corresponding browser request must be made.
To avoid error "language-url plugin does not exist", a simple browser request
to http://liberal.test:8001 must be made before any script, preferably after build.
The request must be repeated after executing `vendor/bin/drush cr` (?)

To avoid "image-scale plugin not found" when executing `migration.php`,
first upload a random article image from the browser.

Rebuilding fixes most of the problems. Mysql dump including lib_cache* tables
may be the source of some problems.

## CI

After the operation, the plan is to upload the sql dump along with the generated migration files
in the dev server avoiding including them in git because of their size. In order
to upload them, first export the dump (`bash db.export.sh`), then from a wsl shell,
run: `upload.liberal.migration`. The bash function is defined in `$HOME/.profile`.
