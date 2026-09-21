#!/bin/bash

set -e

URI="${LIBERAL_APP_HOST_URL}"

echo "[d11-migrate] Enter maintenance mode"
drush --uri="$URI" state:set system.maintenance_mode 1 --input-format=integer -y

echo "[d11-migrate] Truncate stale batch entries"
drush --uri="$URI" php:eval "\Drupal::database()->truncate('batch')->execute();"


echo "[d11-migrate] Enable help (transient — needed for taxonomy_import route resolution)"
drush --uri="$URI" en help -y

echo "[d11-migrate] First updatedb pass (expect partial fail at HelpController — alias column added)"
set +e
drush --uri="$URI" updatedb -y --no-cache-clear
set -e

echo "[d11-migrate] Verify router.alias column exists"
drush --uri="$URI" php:eval '
if (!\Drupal::database()->schema()->fieldExists("router", "alias")) {
echo "FATAL: router.alias still missing — system_update_11201 did not run\n";
exit(1);
}
echo "router.alias present\n";
'

echo "[d11-migrate] Second updatedb pass (finish remaining updates)"
drush --uri="$URI" updatedb -y --no-cache-clear

echo "[d11-migrate] Cleanup obsolete modules"
drush --uri="$URI" pm:uninstall taxonomy_import -y || true
drush --uri="$URI" pm:uninstall help -y || true

echo "[d11-migrate] Truncate stale batch entries (post-update)"
drush --uri="$URI" php:eval "\Drupal::database()->truncate('batch')->execute();"

drush --uri="$URI" updatedb -y

echo "[d11-migrate] Rebuild caches"
drush --uri="$URI" cache:rebuild

echo "[d11-migrate] Exit maintenance mode"
drush --uri="$URI" state:set system.maintenance_mode 0 --input-format=integer -y

echo "[d11-migrate] D11 database upgrade completed successfully!"