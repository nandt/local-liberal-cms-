#!/bin/bash
# One-off μεταπτώσεις που τρέχουν ΜΕΤΑ το liberal-migrate.sh, μία φορά ανά περιβάλλον.
#
# Δεν ανήκουν στο migrate.sh: εκείνο τρέχει σε κάθε deploy και πρέπει να είναι
# αβλαβές. Εδώ μετακινούμε περιεχόμενο και σβήνουμε πεδία.
#
#   liberal-finalize.sh                  backfill + podcast migration + unpublish
#   liberal-finalize.sh --limit=10       δοκιμαστικά, μόνο 10 podcasts
#   liberal-finalize.sh --force          ξανατρέχει παρότι έχει ήδη ολοκληρωθεί
#   liberal-finalize.sh --with-cleanup   + οι ΜΗ ΑΝΑΣΤΡΕΨΙΜΕΣ διαγραφές
#
# Τα προεπιλεγμένα βήματα είναι όλα επαναλήψιμα: το backfill κάνει merge upsert,
# το migration παρακάμπτει ό,τι έχει ήδη περάσει, το unpublish είναι idempotent.

set -e

cd /opt/drupal
DRUSH="vendor/bin/drush"
STATE_KEY="liberal.finalize_completed"

LIMIT=""
FORCE=0
WITH_CLEANUP=0
for arg in "$@"; do
  case "$arg" in
    --limit=*) LIMIT="${arg#--limit=}" ;;
    --force) FORCE=1 ;;
    --with-cleanup) WITH_CLEANUP=1 ;;
    *) echo "[finalize] άγνωστη παράμετρος: $arg"; exit 2 ;;
  esac
done

DONE=$($DRUSH php:eval "print \Drupal::state()->get('$STATE_KEY') ? '1' : '0';" 2>/dev/null | tail -1)
if [ "$DONE" = "1" ] && [ "$FORCE" -eq 0 ] && [ "$WITH_CLEANUP" -eq 0 ]; then
  echo "[finalize] έχει ήδη ολοκληρωθεί σε αυτό το περιβάλλον — παραλείπεται"
  echo "[finalize] για επανάληψη: liberal-finalize.sh --force"
  exit 0
fi

if [ "$WITH_CLEANUP" -eq 0 ]; then
  echo "[finalize] ── Α. social sharing backfill"
  $DRUSH unicorn_socials_post:backfill_social_sharing 2>&1 || echo "[finalize] WARN backfill απέτυχε"

  echo "[finalize] ── Β. podcasts → video articles"
  if [ -n "$LIMIT" ]; then
    $DRUSH liberal_podcasts:migrate --limit="$LIMIT" 2>&1 || echo "[finalize] WARN migration απέτυχε"
  else
    $DRUSH liberal_podcasts:migrate 2>&1 || echo "[finalize] WARN migration απέτυχε"
  fi

  # Το migration μεταφέρει το path alias στο νέο node αλλά αφήνει το podcast
  # δημοσιευμένο, οπότε το ίδιο περιεχόμενο υπάρχει δύο φορές. Το unpublish το
  # κόβει από site/sitemap/feeds χωρίς να χαθεί τίποτα — αναστρέψιμο.
  echo "[finalize] ── Γ. unpublish όσα podcasts έμειναν δημοσιευμένα"
  $DRUSH php:eval '
  $storage = \Drupal::entityTypeManager()->getStorage("node");
  $ids = \Drupal::entityQuery("node")->condition("type", "podcast")
    ->condition("status", 1)->accessCheck(FALSE)->execute();
  foreach (array_chunk($ids, 50) as $chunk) {
    foreach ($storage->loadMultiple($chunk) as $node) { $node->setUnpublished()->save(); }
    $storage->resetCache($chunk);
  }
  print "[finalize] unpublished " . count($ids) . " podcast nodes\n";
  ' 2>&1 || echo "[finalize] WARN unpublish απέτυχε"

  $DRUSH php:eval "\Drupal::state()->set('$STATE_KEY', TRUE);" >/dev/null 2>&1 || true
  echo "[finalize] done — τρέξε liberal-verify.sh για την κατάσταση"
  exit 0
fi

# ── ΜΗ ΑΝΑΣΤΡΕΨΙΜΑ. Μόνο αφού επαληθευτεί ότι η μετάπτωση είναι σωστή.
# Τα notes του backend το λένε ρητά: "run only after verification".
echo "[finalize] ⛔ ΜΗ ΑΝΑΣΤΡΕΨΙΜΕΣ ΔΙΑΓΡΑΦΕΣ"
echo "[finalize] σβήνει: τα 6 legacy social πεδία, όλα τα podcast nodes,"
echo "[finalize]         το podcast content type, τα field storages του, τον map table"
printf "[finalize] γράψε ακριβώς DELETE για να συνεχίσεις: "
read -r CONFIRM
if [ "$CONFIRM" != "DELETE" ]; then
  echo "[finalize] ακυρώθηκε"
  exit 1
fi

$DRUSH unicorn_socials_post:delete_legacy_social_fields 2>&1 || echo "[finalize] WARN delete_legacy_social_fields απέτυχε"
$DRUSH liberal_podcasts:cleanup 2>&1 || echo "[finalize] WARN cleanup απέτυχε"
echo "[finalize] cleanup done — τα δεδομένα των πεδίων τα καθαρίζει σταδιακά το cron"
