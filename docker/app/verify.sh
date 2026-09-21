#!/bin/bash
# Post-deploy έλεγχος: τι έπρεπε να έχει στηθεί μετά το build + migrate.
# Τυπώνει OK/FAIL ανά έλεγχο και γυρίζει exit 1 αν κάτι λείπει.

cd /opt/drupal
DRUSH="vendor/bin/drush"

# Το exit() μέσα σε php:eval κάνει το drush να τερματίζει "abnormally" και να γυρίζει 1
# ακόμα κι όταν όλα περνούν, οπότε ο κώδικας τυπώνει μετρητή και ο έλεγχος γίνεται εδώ.
OUTPUT=$($DRUSH php:eval '
$fail = 0;
$check = function (string $label, bool $ok, string $detail = "") use (&$fail) {
  if (!$ok) { $fail++; }
  printf("%-46s %s%s\n", $label, $ok ? "OK" : "FAIL", $detail === "" ? "" : "  " . $detail);
};

$moduleHandler = \Drupal::moduleHandler();
$missing = [];
foreach (glob("/opt/drupal/web/modules/custom/*", GLOB_ONLYDIR) as $dir) {
  $name = basename($dir);
  if (!file_exists("$dir/$name.info.yml")) { continue; }
  $info = \Symfony\Component\Yaml\Yaml::parseFile("$dir/$name.info.yml");
  if (!empty($info["hidden"])) { continue; }
  if (!$moduleHandler->moduleExists($name)) { $missing[] = $name; }
}
$check("custom modules enabled", $missing === [], $missing ? "λείπουν: " . implode(", ", $missing) : "");

$scopeStorage = \Drupal::entityTypeManager()->getStorage("oauth2_scope");
$wantScopes = ["liberal", "dashboard", "admin", "editor", "commercial", "client"];
$missingScopes = array_values(array_filter($wantScopes, fn($id) => $scopeStorage->load($id) === NULL));
$check("OAuth scopes", $missingScopes === [], $missingScopes ? "λείπουν: " . implode(", ", $missingScopes) : implode(", ", $wantScopes));

$roleStorage = \Drupal::entityTypeManager()->getStorage("user_role");
$wantRoles = ["administrator", "liberal_content_editor", "liberal_commercial", "liberal_client"];
$missingRoles = array_values(array_filter($wantRoles, fn($id) => $roleStorage->load($id) === NULL));
$check("roles", $missingRoles === [], $missingRoles ? "λείπουν: " . implode(", ", $missingRoles) : "");

$manager = \Drupal::entityDefinitionUpdateManager();
$check("section entity type installed", $manager->getEntityType("section") !== NULL);

$fieldManager = \Drupal::service("entity_field.manager");
$articleFields = $fieldManager->getFieldDefinitions("node", "article_liberal");
$wantFields = ["field_attached_video_code", "field_attached_video_source", "field_attached_video_title",
  "field_full_width_image", "field_kentriki_fotografia", "field_liberal_category",
  "field_vid_article_duration", "field_vid_article_episode", "field_vid_article_height",
  "field_vid_article_series", "field_vid_article_source_code", "field_vid_article_width"];
$missingFields = array_values(array_filter($wantFields, fn($n) => !isset($articleFields[$n])));
$check("article_liberal fields", $missingFields === [],
  sprintf("%d/%d%s", count($wantFields) - count($missingFields), count($wantFields),
    $missingFields ? " — λείπουν: " . implode(", ", $missingFields) : ""));

$clientId = getenv("OAUTH_CLIENT_ID");
if ($clientId) {
  $ids = \Drupal::entityQuery("consumer")->condition("client_id", $clientId)->accessCheck(FALSE)->execute();
  $consumer = $ids ? \Drupal::entityTypeManager()->getStorage("consumer")->load(reset($ids)) : NULL;
  $check("FE consumer exists", $consumer !== NULL, $clientId);
  if ($consumer) {
    $granted = array_column($consumer->get("scopes")->getValue(), "scope_id");
    $check("consumer has dashboard scope", in_array("dashboard", $granted, TRUE), implode(", ", $granted));
  }
}
else {
  print "consumer check skipped — OAUTH_CLIENT_ID δεν έχει οριστεί\n";
}

$broken = [];
foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $type) {
  if (!$type instanceof \Drupal\Core\Entity\ContentEntityTypeInterface) { continue; }
  try {
    $storage = \Drupal::entityTypeManager()->getStorage($id);
    if (!$storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage) { continue; }
    $mapping = $storage->getTableMapping();
    foreach ($mapping->getTableNames() as $table) { $mapping->getAllColumns($table); }
  }
  catch (\Throwable $e) { $broken[] = $id; }
}
$check("entity table mappings", $broken === [], $broken ? implode(", ", $broken) : "");

$pending = array_keys(\Drupal::service("update.post_update_registry")->getPendingUpdateInformation());
$check("no pending post updates", $pending === [], $pending ? implode(", ", $pending) : "");

// Το config/sync είναι το δηλωμένο συμβόλαιο του API· η βάση είναι τι ισχύει όντως.
// Δεν ελέγχουμε "όλα τα πεδία εκτεθειμένα" — η πολιτική είναι default_disabled και το
// 39% των πεδίων είναι σκόπιμα κλειστά. Ελέγχουμε απόκλιση: ό,τι δηλώνει το repo
// ανοιχτό πρέπει να είναι ανοιχτό εδώ. Καμία χειρόγραφη λίστα, οπότε ό,τι προστεθεί
// αύριο καλύπτεται μόλις γίνει commit.
$syncDir = "/opt/drupal/config/sync";
$declared = \Symfony\Component\Yaml\Yaml::parseFile("$syncDir/jsonapi_extras.settings.yml");
$runtime = \Drupal::config("jsonapi_extras.settings")->get("path_prefix");
$check("JSON:API path prefix", $runtime === ($declared["path_prefix"] ?? NULL),
  $runtime === ($declared["path_prefix"] ?? NULL) ? $runtime : sprintf("%s, το repo δηλώνει %s", $runtime, $declared["path_prefix"] ?? "—"));

$resourceStorage = \Drupal::entityTypeManager()->getStorage("jsonapi_resource_config");
$files = glob("$syncDir/jsonapi_resource_config.*.yml");
$notApplied = [];
$drifted = [];
foreach ($files as $file) {
  $cfg = \Symfony\Component\Yaml\Yaml::parseFile($file);
  $id = $cfg["id"] ?? NULL;
  if ($id === NULL) { continue; }
  $entity = $resourceStorage->load($id);
  if ($entity === NULL) { $notApplied[] = $id; continue; }
  $live = (array) $entity->get("resourceFields");
  $missing = [];
  foreach (($cfg["resourceFields"] ?? []) as $name => $definition) {
    if (!empty($definition["disabled"])) { continue; }
    if (!isset($live[$name]) || !empty($live[$name]["disabled"])) { $missing[] = $name; }
  }
  if ($missing !== []) { $drifted[] = $id . " (" . count($missing) . ": " . implode(", ", array_slice($missing, 0, 4)) . (count($missing) > 4 ? ", …" : "") . ")"; }
}
$check("resource configs applied", $notApplied === [],
  $notApplied ? "δεν εφαρμόστηκαν: " . implode(", ", $notApplied) : sprintf("%d/%d από config/sync", count($files), count($files)));
$check("exposed fields match config/sync", $drifted === [], $drifted ? implode(" | ", $drifted) : "");

$metatagStorage = \Drupal::entityTypeManager()->getStorage("metatag_defaults");
$metatagFiles = glob("$syncDir/metatag.metatag_defaults.*.yml");
$mtNotApplied = [];
$mtDrifted = [];
foreach ($metatagFiles as $file) {
  $cfg = \Symfony\Component\Yaml\Yaml::parseFile($file);
  $id = $cfg["id"] ?? NULL;
  if ($id === NULL) { continue; }
  $entity = $metatagStorage->load($id);
  if ($entity === NULL) { $mtNotApplied[] = $id; continue; }
  $live = (array) $entity->get("tags");
  $missing = [];
  foreach (($cfg["tags"] ?? []) as $name => $value) {
    if ($value === "" || $value === NULL) { continue; }
    if (!isset($live[$name]) || $live[$name] === "") { $missing[] = $name; }
  }
  if ($missing !== []) { $mtDrifted[] = $id . " (" . count($missing) . ": " . implode(", ", array_slice($missing, 0, 4)) . (count($missing) > 4 ? ", …" : "") . ")"; }
}
$check("metatag defaults applied", $mtNotApplied === [],
  $mtNotApplied ? "δεν εφαρμόστηκαν: " . implode(", ", $mtNotApplied) : sprintf("%d/%d από config/sync", count($metatagFiles), count($metatagFiles)));
$check("metatag tags match config/sync", $mtDrifted === [], $mtDrifted ? implode(" | ", $mtDrifted) : "");

$requiredSchemaTags = ["schema_article_date_modified", "schema_article_keywords", "schema_article_section", "schema_article_publisher"];
$articleDefaults = $metatagStorage->load("node__article_liberal");
$liveArticleTags = $articleDefaults === NULL ? [] : (array) $articleDefaults->get("tags");
$missingSchema = array_values(array_filter($requiredSchemaTags, static fn ($t) => empty($liveArticleTags[$t])));
$check("article structured data tags", $missingSchema === [], $missingSchema ? "λείπουν: " . implode(", ", $missingSchema) : count($requiredSchemaTags) . "/" . count($requiredSchemaTags));

$routeExists = \Drupal::service("router.route_provider")->getRoutesByNames(["unicorn_api_alterations.metatag_route"]) !== [];
$check("metatag-route endpoint", $routeExists, $routeExists ? "/customapi/metatag-route" : "το route δεν είναι καταχωρημένο");

$videoTerm = \Drupal::entityTypeManager()->getStorage("taxonomy_term")
  ->loadByProperties(["vid" => "eidi_arthron", "name" => "Βίντεο"]);
$check("video article type term", $videoTerm !== [], $videoTerm ? "tid " . reset($videoTerm)->id() : "λείπει από το eidi_arthron");

// Update hooks: ποιο πέρασε, ποιο έμεινε πίσω. Το updb τρέχει σε κάθε deploy, οπότε
// ένα pending update σημαίνει ότι κάτι έσκασε — και μέχρι τώρα φαινόταν μόνο σε ένα
// WARN που κύλαγε στα logs.
// Τα update functions τα διαβάζουμε από τον ίδιο τον κώδικα και τα συγκρίνουμε με το
// schema version της βάσης. Το getAvailableUpdates() γυρίζει μόνο τα εκκρεμή, οπότε
// δεν λέει πόσα πέρασαν — και θέλουμε να φαίνονται και τα δύο.
$registry = \Drupal::service("update.update_hook_registry");
$pendingUpdates = [];
$appliedCount = 0;
$definedCount = 0;
foreach (glob("/opt/drupal/web/modules/custom/*", GLOB_ONLYDIR) as $dir) {
  $module = basename($dir);
  if (!\Drupal::moduleHandler()->moduleExists($module)) { continue; }
  $file = $dir . "/" . $module . ".install";
  if (!file_exists($file)) { continue; }
  preg_match_all("/function " . preg_quote($module, "/") . "_update_(\d+)/", file_get_contents($file), $m);
  if (empty($m[1])) { continue; }
  $installed = (int) $registry->getInstalledVersion($module);
  foreach ($m[1] as $number) {
    $definedCount++;
    if ((int) $number > $installed) { $pendingUpdates[] = $module . "_update_" . $number; }
    else { $appliedCount++; }
  }
}
$check("update hooks applied", $pendingUpdates === [],
  $pendingUpdates
    ? sprintf("%d/%d πέρασαν · εκκρεμούν: %s", $appliedCount, $definedCount, implode(", ", $pendingUpdates))
    : sprintf("%d/%d", $appliedCount, $definedCount));

// Fresh install προσπερνά τα update hooks, οπότε ό,τι ζει μόνο εκεί δεν φτάνει ποτέ σε
// νέο περιβάλλον. Το unicorn_config_updates τα ξανατρέχει από hook_install μέσω μιας
// χειρόγραφης λίστας — που είναι εύκολο να ξεχαστεί όταν προστεθεί νέο update.
$installFile = "/opt/drupal/web/modules/custom/unicorn_config_updates/unicorn_config_updates.install";
$unregistered = [];
if (file_exists($installFile)) {
  $source = file_get_contents($installFile);
  preg_match_all("/function unicorn_config_updates_update_(\d+)/", $source, $defined);
  preg_match("/foreach \(\[([^\]]*)\] as \\\$number\)/", $source, $listed);
  $registered = [];
  if (!empty($listed[1])) { preg_match_all("/\d+/", $listed[1], $found); $registered = $found[0]; }
  foreach ($defined[1] as $number) {
    // Το 11005 είναι το ίδιο το wrapper που καλεί τη λίστα, δεν μπαίνει μέσα της.
    if ($number === "11005") { continue; }
    if (!in_array($number, $registered, TRUE)) { $unregistered[] = $number; }
  }
}
$check("config updates reach fresh installs", $unregistered === [],
  $unregistered ? "εκτός λίστας _apply_all: " . implode(", ", $unregistered) : count($registered ?? []) . " καταχωρημένα");

// Κατάσταση των one-off μεταπτώσεων του liberal-finalize.sh. INFO, όχι FAIL — δεν
// είναι λάθος να μην έχουν τρέξει ακόμα· θέλουμε απλώς να φαίνεται πού βρισκόμαστε.
$info = function (string $label, string $detail): void { printf("%-46s %s  %s\n", $label, "INFO", $detail); };

$db = \Drupal::database();
$podcastsTotal = $db->schema()->tableExists("node_field_data")
  ? (int) $db->select("node_field_data", "n")->condition("n.type", "podcast")->countQuery()->execute()->fetchField() : 0;
$podcastsPublished = $podcastsTotal
  ? (int) $db->select("node_field_data", "n")->condition("n.type", "podcast")->condition("n.status", 1)->countQuery()->execute()->fetchField() : 0;
$migrated = $db->schema()->tableExists("liberal_podcasts_migration_map")
  ? (int) $db->select("liberal_podcasts_migration_map", "m")->countQuery()->execute()->fetchField() : 0;
$info("podcast migration", $podcastsTotal === 0 && $migrated === 0
  ? "τίποτα να μεταφερθεί"
  : sprintf("%d migrated · %d podcast απομένουν (%d δημοσιευμένα)", $migrated, $podcastsTotal, $podcastsPublished));

$sharingRows = $db->schema()->tableExists("unicorn_social_sharing")
  ? (int) $db->select("unicorn_social_sharing", "s")->countQuery()->execute()->fetchField() : NULL;
$legacySocial = array_values(array_filter([
  "field_post_fb_liberal_counter", "field_post_fb_liberal_latest_tim",
  "field_post_twitter_liberal_count", "field_post_twitter_liberal_lates",
  "field_notification_liberal_count", "field_notification_liberal_time",
], fn($n) => isset($articleFields[$n])));
$info("socials backfill", $sharingRows === NULL
  ? "ο πίνακας unicorn_social_sharing δεν υπάρχει"
  : sprintf("%d εγγραφές · %d legacy πεδία ακόμα στο article_liberal", $sharingRows, count($legacySocial)));

// Πεδία που συμπληρώνει ο συντάκτης αλλά δεν βλέπει ο FE. Τα weights και οι legacy
// counters είναι σκόπιμα κλειστά, γι αυτό INFO: αν ο αριθμός αλλάξει, κάποιο νέο
// πεδίο ξεχάστηκε από το JSON:API.
$formDisplay = \Drupal::entityTypeManager()->getStorage("entity_form_display")->load("node.article_liberal.default");
$notExposed = [];
if ($formDisplay !== NULL) {
  $articleResource = (array) ($resourceStorage->load("node--article_liberal")?->get("resourceFields") ?? []);
  foreach (array_keys($formDisplay->getComponents()) as $name) {
    if (!str_starts_with($name, "field_")) { continue; }
    if (!isset($articleResource[$name]) || !empty($articleResource[$name]["disabled"])) { $notExposed[] = $name; }
  }
}
$weights = count(array_filter($notExposed, fn($n) => str_ends_with($n, "_weight")));
$info("editor fields not in JSON:API", sprintf("%d (%d weights, %d άλλα)", count($notExposed), $weights, count($notExposed) - $weights));

print "FINALIZE_DONE=" . (\Drupal::state()->get("liberal.finalize_completed") ? 1 : 0) . "\n";
print "VERIFY_FAILURES=" . $fail . "\n";
' 2>&1)

echo "$OUTPUT" | grep -vE "^(VERIFY_FAILURES|FINALIZE_DONE)="
FAILURES=$(printf '%s\n' "$OUTPUT" | sed -n 's/^VERIFY_FAILURES=//p' | tail -1)
FINALIZE_DONE=$(printf '%s\n' "$OUTPUT" | sed -n 's/^FINALIZE_DONE=//p' | tail -1)

if [ -z "$FAILURES" ]; then
  echo
  echo "ΔΕΝ ΟΛΟΚΛΗΡΩΘΗΚΕ — το drush δεν μπόρεσε να τρέξει τους ελέγχους"
  exit 2
fi

if [ "$FAILURES" != "0" ]; then
  echo
  echo "$FAILURES έλεγχοι απέτυχαν"
  exit 1
fi

if [ "$FINALIZE_DONE" != "1" ]; then
  echo
  echo "BACKEND OK — αλλά ΕΚΚΡΕΜΕΙ η μετάπτωση περιεχομένου (podcasts + social backfill)."
  echo "Τρέξε: liberal-finalize.sh"
  exit 3
fi

echo
echo "ΟΛΑ ΕΝΤΑΞΕΙ"
exit 0
