#!/bin/bash
# One-time headless transition — uninstall obsolete/varnish modules.
# Run ONCE while the old image (με τα module files) είναι ενεργό:
#   docker exec liberal-cms-drupal /usr/local/bin/liberal-transition.sh
set -e

cd /opt/drupal
DRUSH="vendor/bin/drush"

echo "[transition] clearing varnish purgers from config"
$DRUSH php:eval '
$c = \Drupal::configFactory()->getEditable("purge.plugins");
$c->set("purgers", array_values(array_filter($c->get("purgers") ?: [], fn($p) => strpos($p["plugin_id"] ?? "", "varnish") === false)));
$c->set("processors", array_values(array_filter($c->get("processors") ?: [], fn($p) => ($p["plugin_id"] ?? "") !== "lateruntime")));
$c->save();
' >/dev/null 2>&1 || true

echo "[transition] uninstalling obsolete + varnish modules (only those still enabled)"
TO_REMOVE=$($DRUSH php:eval '
$candidates = ["liberal_site_tools","liberal_async_blocks","liberal_dashboard_app","liberal_jsonapi_cache_tags","varnish_purger","varnish_purge_tags","purge_processor_lateruntime","purge_queuer_coretags"];
$mh = \Drupal::moduleHandler();
print implode(" ", array_filter($candidates, fn($m) => $mh->moduleExists($m)));
' 2>/dev/null | tail -1)
if [ -n "$TO_REMOVE" ]; then
  echo "[transition] uninstalling:$TO_REMOVE"
  $DRUSH pm:uninstall -y $TO_REMOVE || true
else
  echo "[transition] no obsolete/varnish modules enabled — already clean"
fi

$DRUSH cr >/dev/null 2>&1 || true
echo "[transition] done"
