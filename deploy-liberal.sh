#!/bin/bash
set -e
cd /home/liberal

echo "Stopping running deployment"
docker compose -f docker-compose.yml -f docker-compose.test-env.yml down

echo "Pulling new images"
docker compose -f docker-compose.yml -f docker-compose.test-env.yml pull

echo "Starting deployment"
docker compose -f docker-compose.yml -f docker-compose.test-env.yml up -d

echo "Waiting for MariaDB to be ready"
until docker exec liberal-cms-mariadb mariadb -uliberal -p"${MARIADB_PASSWORD:-liberal}" -e "SELECT 1" >/dev/null 2>&1; do
  sleep 3
done

echo "Enter maintenance mode"
docker exec liberal-cms-drupal vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer -y

# MariaDB engine upgrade — dev server only (staging/prod = AWS RDS, managed by Amazon)
echo "MariaDB engine upgrade"
docker exec liberal-cms-mariadb mariadb-upgrade -u root -p"${MARIADB_PASSWORD:-liberal}" --force || true

# Truncate stale batch entries from old D10 dump
echo "Truncating stale batch entries"
docker exec liberal-cms-drupal drush php:eval "\Drupal::database()->truncate('batch')->execute();"

# First updatedb pass — runs schema updates including system_update_11201 (adds router.alias).
# Expected to fail partway when admin_audit_trail post-step hits taxonomy_import's HelpController
# reference. set +e so partial failure does not abort the script.
echo "First updatedb pass (expect partial fail at HelpController — alias column will be added)"
set +e
docker exec liberal-cms-drupal drush updatedb -y --no-cache-clear
set -e

# Verify alias column was added by the partial run
echo "Verify router.alias column exists"
docker exec liberal-cms-drupal drush php:eval '
if (!\Drupal::database()->schema()->fieldExists("router", "alias")) {
  echo "FATAL: router.alias still missing — system_update_11201 did not run\n";
  exit(1);
}
echo "router.alias present\n";
'

# Now help can be enabled — router rebuild can INSERT into lib_router.alias
echo "Enable help (transient — needed for taxonomy_import route resolution)"
docker exec liberal-cms-drupal drush en help -y

# Second updatedb pass — finishes remaining updates now that HelpController is resolvable
echo "Second updatedb pass (finish remaining updates)"
docker exec liberal-cms-drupal drush updatedb -y --no-cache-clear

echo "Cleanup obsolete modules"
docker exec liberal-cms-drupal drush pm:uninstall taxonomy_import -y || true
docker exec liberal-cms-drupal drush pm:uninstall help -y || true

echo "Applying custom translations"
docker exec liberal-cms-drupal drush locale:import el /opt/drupal/custom-el.po --type=customized --override=all -y

echo "Rebuilding Drupal caches"
docker exec liberal-cms-drupal drush cr

echo "Exit maintenance mode"
docker exec liberal-cms-drupal vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer -y
