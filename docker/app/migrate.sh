#!/bin/bash
set -e

cd /opt/drupal
DRUSH="vendor/bin/drush"

# Readiness + core.extension reconciliation ΠΡΙΝ από κάθε drush: με prod βάση + headless
# κώδικα ο DrupalKernel πεθαίνει στο discoverServiceProviders() (enabled module δείχνει σε
# κλάση module που δεν είναι enabled), οπότε δεν σηκώνεται ούτε drush. Άρα PDO κατευθείαν —
# χωρίς Drupal bootstrap και χωρίς mysql client, που ο dev image δεν τον έχει.
echo "[migrate] waiting for MariaDB + reconciling core.extension..."
php <<'PRE_BOOTSTRAP'
<?php
require "/opt/drupal/vendor/autoload.php";

use Symfony\Component\Yaml\Yaml;

$prefix = getenv("MARIADB_PREFIX") ?: "";
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
  getenv("MARIADB_HOST"), getenv("MARIADB_PORT") ?: "3306", getenv("MARIADB_DATABASE"));

$pdo = NULL;
for ($i = 0; $i < 600; $i++) {
  try {
    $try = new PDO($dsn, getenv("MARIADB_USER"), getenv("MARIADB_PASSWORD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $count = (int) $try->query("SELECT COUNT(*) FROM {$prefix}node_field_data")->fetchColumn();
    if ($count > 0) {
      $pdo = $try;
      echo "[migrate] MariaDB ready ($count node rows)\n";
      break;
    }
  }
  catch (Throwable $e) {}
  if ($i > 0 && $i % 15 === 0) { echo "[migrate] still waiting (attempt $i/600)...\n"; }
  sleep(2);
}
if (!$pdo) { echo "[migrate] ERROR MariaDB never became ready\n"; exit(1); }

$available = [];
foreach (["/opt/drupal/web/modules", "/opt/drupal/web/core/modules", "/opt/drupal/web/profiles", "/opt/drupal/web/core/profiles"] as $base) {
  if (!is_dir($base)) { continue; }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) {
    if (substr($f->getFilename(), -9) === ".info.yml") {
      $available[basename($f->getFilename(), ".info.yml")] = $f->getPathname();
    }
  }
}

$row = $pdo->prepare("SELECT data FROM {$prefix}config WHERE collection = '' AND name = 'core.extension'");
$row->execute();
$data = $row->fetchColumn();
if ($data === FALSE) { echo "[migrate] ERROR core.extension not found\n"; exit(1); }
$extension = unserialize($data);
$modules = $extension["module"] ?? [];
$profile = $extension["profile"] ?? "";

$purged = [];
foreach (array_keys($modules) as $name) {
  if (!isset($available[$name]) && $name !== $profile) {
    unset($modules[$name]);
    $purged[] = $name;
  }
}

// Ένα enabled module μπορεί να δηλώνει dependency που η παλιά βάση δεν έχει enabled (π.χ. το
// liberal_stocks_app απέκτησε unicorn_core στο refactor). Αν το dependency δεν έχει .install,
// είναι καθαρό code module και μπαίνει με ασφάλεια εδώ — τα υπόλοιπα τα αναλαμβάνει το drush en.
$added = [];
$pending = array_keys($modules);
while ($pending) {
  $name = array_shift($pending);
  if (!isset($available[$name])) { continue; }
  $info = Yaml::parseFile($available[$name]);
  foreach ($info["dependencies"] ?? [] as $dependency) {
    $dep = substr($dependency, (int) strpos($dependency, ":") + 1);
    if (isset($modules[$dep]) || !isset($available[$dep])) { continue; }
    if (file_exists(dirname($available[$dep]) . "/$dep.install")) { continue; }
    $modules[$dep] = 0;
    $added[] = $dep;
    $pending[] = $dep;
  }
}

if ($purged || $added) {
  $extension["module"] = $modules;
  $update = $pdo->prepare("UPDATE {$prefix}config SET data = :data WHERE collection = '' AND name = 'core.extension'");
  $update->execute([":data" => serialize($extension)]);
  $delete = $pdo->prepare("DELETE FROM {$prefix}key_value WHERE collection = 'system.schema' AND name = :name");
  foreach ($purged as $name) { $delete->execute([":name" => $name]); }
  // Με Redis backend οι cache πίνακες μπορεί να μην υπάρχουν — δεν είναι λόγος να σταματήσει.
  foreach (["cache_bootstrap", "cache_discovery", "cache_config"] as $table) {
    try { $pdo->exec("DELETE FROM {$prefix}{$table}"); } catch (Throwable $e) {}
  }
}
echo $purged ? "[migrate] pre-bootstrap purged: " . implode(", ", $purged) . "\n" : "[migrate] pre-bootstrap: no stale modules\n";
echo $added ? "[migrate] pre-bootstrap added missing dependencies: " . implode(", ", $added) . "\n" : "[migrate] pre-bootstrap: no missing dependencies\n";
PRE_BOOTSTRAP

# Η βάση προέρχεται από το monolithic production και έχει enabled δεκάδες legacy
# modules (liberal_ads, liberal_dashboard_app, ...) που ΔΕΝ υπάρχουν στον headless
# κώδικα. Όσο μένουν στο core.extension, το ExtensionList σκάει σε κάθε module list
# rebuild ("The module X does not exist") — και ΚΑΘΕ $DRUSH en παρακάτω αποτυγχάνει
# σιωπηλά (>/dev/null || true), οπότε consumers/simple_oauth δεν εγκαθίστανται ποτέ
# και όλο το site 500άρει. Τα καθαρίζουμε από τη βάση πριν αγγίξουμε οτιδήποτε άλλο.
echo "[migrate] purge modules enabled in DB but absent from code"
$DRUSH php:eval '
$available = [];
foreach (["/opt/drupal/web/modules","/opt/drupal/web/core/modules","/opt/drupal/web/profiles","/opt/drupal/web/core/profiles"] as $base) {
  if (!is_dir($base)) { continue; }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) {
    if (substr($f->getFilename(), -9) === ".info.yml") { $available[basename($f->getFilename(), ".info.yml")] = TRUE; }
  }
}
$profile = \Drupal::installProfile();
$c = \Drupal::configFactory()->getEditable("core.extension");
$m = $c->get("module") ?? [];
$rm = [];
foreach (array_keys($m) as $name) {
  if (!isset($available[$name]) && $name !== $profile) {
    unset($m[$name]);
    \Drupal::keyValue("system.schema")->delete($name);
    $rm[] = $name;
  }
}
if ($rm) { $c->set("module", $m)->save(); echo "[migrate] purged stale modules: " . implode(", ", $rm) . "\n"; }
else { echo "[migrate] no stale modules\n"; }
' 2>&1 || echo "[migrate] WARN stale-module purge failed (continuing)"

# Τα διαγραμμένα modules αφήνουν rest_resource_config που δείχνει σε ανύπαρκτο plugin (π.χ.
# custom_dashboard_mass_region_update_resource από το liberal_dashboard_app). Κάθε module
# install μετά σκάει με PluginNotFoundException, άρα πρέπει να φύγουν ΠΡΙΝ από τα enable.
echo "[migrate] purge REST resources with missing plugins"
$DRUSH php:eval '
$valid = array_keys(\Drupal::service("plugin.manager.rest")->getDefinitions());
$cf = \Drupal::configFactory();
$removed = [];
foreach ($cf->listAll("rest.resource.") as $name) {
  $plugin = $cf->get($name)->get("plugin_id");
  if ($plugin && !in_array($plugin, $valid, TRUE)) {
    $cf->getEditable($name)->delete();
    $removed[] = $plugin;
  }
}
if ($removed) { echo "[migrate] purged orphan REST resources: " . implode(", ", $removed) . "\n"; }
else { echo "[migrate] no orphan REST resources\n"; }
' 2>&1 || echo "[migrate] WARN REST purge failed (continuing)"

$DRUSH cr >/dev/null 2>&1 || true

# Μέσω schema API, όχι sql:query: ο dev image δεν έχει mysql client, οπότε το sql:query
# γύριζε πάντα κενό και το reset παρακάτω έσβηνε τα OAuth config σε ΚΑΘΕ εκτέλεση.
HAS_CONSUMER=$($DRUSH php:eval 'print \Drupal::database()->schema()->tableExists("consumer") ? "1" : "0";' 2>/dev/null | tail -1)
if [ "$HAS_CONSUMER" != "1" ]; then
  echo "[migrate] OAuth tables missing — resetting"
  $DRUSH php:eval '
  $cf = \Drupal::configFactory();
  foreach ($cf->listAll() as $n) {
    if (strpos($n, "oauth") !== false || strpos($n, "consumer") !== false) {
      $cf->getEditable($n)->delete();
    }
  }
  $c = $cf->getEditable("core.extension");
  $m = $c->get("module");
  foreach (["consumers","simple_oauth","simple_oauth_password_grant"] as $name) { unset($m[$name]); }
  $c->set("module", $m)->save();
  $ks = \Drupal::keyValue("system.schema");
  foreach (["consumers","simple_oauth","simple_oauth_password_grant"] as $name) { $ks->delete($name); }
  $ed = \Drupal::keyValue("entity.definitions.installed");
  foreach (array_keys($ed->getAll()) as $k) {
    if (strpos($k,"consumer.")===0 || strpos($k,"oauth2_")===0) { $ed->delete($k); }
  }
  ' >/dev/null 2>&1 || true
  $DRUSH php:eval '
  $schema = \Drupal::database()->schema();
  foreach (["consumer", "consumer_field_data", "consumer__authorization_code_scopes", "consumer__grant_types", "consumer__scopes", "oauth2_token", "oauth2_token_field_data", "oauth2_token__scopes"] as $table) {
    if ($schema->tableExists($table)) { $schema->dropTable($table); }
  }
  ' >/dev/null 2>&1 || true
  $DRUSH cr >/dev/null 2>&1 || true
fi

ENABLE_FAILED=""

echo "[migrate] enable headless contrib modules"
CONTRIB_MODULES="consumers simple_oauth simple_oauth_password_grant jsonapi_extras jsonapi_resources jsonapi_include jsonapi_image_styles jsonapi_menu_items decoupled_router subrequests redis health_check"
if ! $DRUSH en -y $CONTRIB_MODULES 2>&1; then
  echo "[migrate] WARN bulk contrib enable failed — retrying one by one"
  for m in $CONTRIB_MODULES; do
    $DRUSH en -y "$m" >/dev/null 2>&1 || ENABLE_FAILED="$ENABLE_FAILED $m"
  done
fi

# Κάθε custom module του image, χωρίς χειρόγραφη λίστα — η παλιά κάλυπτε 7 από τα 24 και
# έμεναν εκτός unicorn_core/unicorn_account/unicorn_field_mapper. Μετράνε μόνο φάκελοι με
# αντίστοιχο .info.yml, και όποιος έχει "hidden: true" εξαιρείται (module υπό ανάπτυξη).
CUSTOM_MODULES=""
for dir in /opt/drupal/web/modules/custom/*/; do
  name=$(basename "$dir")
  [ -f "$dir$name.info.yml" ] || continue
  grep -q "^hidden:[[:space:]]*true" "$dir$name.info.yml" && { echo "[migrate] skipping hidden module $name"; continue; }
  CUSTOM_MODULES="$CUSTOM_MODULES $name"
done
echo "[migrate] enable custom modules:$CUSTOM_MODULES"
if ! $DRUSH en -y $CUSTOM_MODULES 2>&1; then
  echo "[migrate] WARN bulk enable failed — retrying one by one"
  for m in $CUSTOM_MODULES; do
    $DRUSH en -y "$m" >/dev/null 2>&1 || ENABLE_FAILED="$ENABLE_FAILED $m"
  done
fi

if [ -n "$ENABLE_FAILED" ]; then
  echo "[migrate] ERROR modules failed to enable:$ENABLE_FAILED"
fi

$DRUSH php:eval '
$s = \Drupal::entityTypeManager()->getStorage("jsonapi_resource_config");
if (!$s->load("node--page")) {
  $rf = [];
  foreach (\Drupal::service("entity_field.manager")->getFieldDefinitions("node", "page") as $n => $d) {
    $rf[$n] = ["fieldName" => $n, "publicName" => $n, "enhancer" => ["id" => ""], "disabled" => FALSE];
  }
  $s->create(["id" => "node--page", "disabled" => FALSE, "path" => "node/page", "resourceType" => "node--page", "third_party_settings" => ["jsonapi_defaults" => ["page_limit" => 50]], "resourceFields" => $rf])->save();
}
' >/dev/null 2>&1 || true

# openapi + swagger docs: τα modules είναι ήδη στο composer.lock (build) και τα
# swagger-ui assets ψήνονται στο image (Dockerfile: web/libraries/swagger-ui/dist).
# Εδώ μένει μόνο το enable — χωρίς runtime composer/curl, χωρίς silent masking.
if [ "$ENV" = "local" ] || [ "$ENV" = "dev" ] || [ "$ENV" = "develop" ]; then
  echo "[migrate] enabling openapi + swagger docs"
  $DRUSH en -y openapi_jsonapi openapi_ui_swagger schemata schemata_json_schema
fi

$DRUSH en -y warmer warmer_cdn warmer_entity >/dev/null 2>&1 || true

$DRUSH php:eval '
$cf = \Drupal::configFactory();
$c = $cf->getEditable("warmer.warmer.sitemap");
$sitemaps = ["/sitemap_index.xml", "/sitemap_news.xml"];
if ($c->isNew()) {
  $c->setData([
    "id" => "sitemap",
    "label" => "Sitemap URLs warmer",
    "description" => "Warms cache for sitemap URLs",
    "frequency" => 300,
    "batchSize" => 10,
    "warmer" => "cdn",
    "configuration" => [
      "sitemaps" => $sitemaps,
      "minPriority" => 0.5,
      "verify" => true,
      "userAgent" => "Liberal-Warmer/1.0",
    ],
    "dependencies" => ["enforced" => ["module" => ["warmer_cdn"]]],
  ])->save();
}
elseif ($c->get("configuration.sitemaps") !== $sitemaps) {
  $c->set("configuration.sitemaps", $sitemaps)->save();
}
' >/dev/null 2>&1 || true

$DRUSH cset jsonapi.settings read_only 0 -y >/dev/null 2>&1 || true

$DRUSH php:eval '
$cf = \Drupal::configFactory();
$ur = $cf->getEditable("jsonapi_extras.jsonapi_resource_config.user--user");
if ($ur->isNew()) {
  $ur->setData([
    "id" => "user--user", "disabled" => false, "path" => "user/user",
    "resourceType" => "user--user", "resourceFields" => [],
    "dependencies" => ["enforced" => ["module" => ["user"]]],
  ])->save();
} else {
  $ur->set("disabled", false)->save();
}
' >/dev/null 2>&1 || true

echo "[migrate] disable maintenance mode"
$DRUSH state:set system.maintenance_mode 0 --input-format=integer >/dev/null 2>&1 || true

echo "[migrate] updb"
$DRUSH updb -y 2>&1 || echo "[migrate] WARN updb failed (continuing)"

# Συγχρονισμός ρόλων/permissions με τον κώδικα σε ΚΑΘΕ deploy: τα update hooks τα προσπερνά
# ο fresh install και το hook_modules_installed fires μόνο σε νέο module install, οπότε ένα
# env που στήθηκε από dump μένει με stale ρόλους. Additive grant — δεν αφαιρεί permissions,
# ώστε ο κοινός production ρόλος liberal_content_editor να μη χάσει τα δικά του.
echo "[migrate] re-provision Liberal roles (sync to code capabilities)"
$DRUSH php:eval '
use Drupal\unicorn_account\Roles\RoleProvisioner;
if (\Drupal::moduleHandler()->moduleExists("unicorn_account")) {
  (new RoleProvisioner(
    \Drupal::entityTypeManager(),
    array_keys(\Drupal::service("user.permissions")->getPermissions()),
  ))->provisionAll();
  \Drupal::entityTypeManager()->getStorage("user_role")->resetCache();
  echo "[migrate] roles re-provisioned\n";
}
' 2>&1 || echo "[migrate] WARN role re-provision failed (continuing)"

# Ίδιος λόγος με τους ρόλους: το article_slot κάθε section γράφεται στο presave, οπότε ένα
# env που στήθηκε από dump κρατάει το πλήθος slots που ίσχυε τότε. Εκτός schema μηχανισμού
# ώστε να τρέχει και εκεί που ο fresh install προσπέρασε τα update hooks. Σώζει μόνο όσα
# sections διαφέρουν από τον κατάλογο, οπότε δεν αγγίζει τίποτα άλλο.
echo "[migrate] re-sync section article_slot (sync to template catalog)"
$DRUSH php:eval '
use Drupal\unicorn_api_alterations\Controller\SectionTemplatesController;
if (\Drupal::moduleHandler()->moduleExists("unicorn_api_alterations")) {
  $slots = array_column(SectionTemplatesController::TEMPLATES, "articleSlots", "id");
  $storage = \Drupal::entityTypeManager()->getStorage("section");
  $fixed = 0;
  foreach ($storage->loadMultiple() as $section) {
    $template = (string) ($section->get("template")->value ?? "");
    if (!isset($slots[$template]) || (int) $section->get("article_slot")->value === $slots[$template]) {
      continue;
    }
    $section->set("article_slot", $slots[$template]);
    $section->save();
    $fixed++;
  }
  echo "[migrate] section article_slot re-synced: $fixed\n";
}
' 2>&1 || echo "[migrate] WARN section slot re-sync failed (continuing)"

# Το DefaultTableMapping::create() βάζει τα entity keys (id/revision/bundle/uuid/langcode)
# στο fieldNames ΧΩΡΙΣ να τα φιλτράρει με τα installed field storage definitions. Αν το
# entity.definitions.installed (prod dump) δεν έχει ορισμό για κάποιο key, το getAllColumns()
# καλεί requiresDedicatedTableStorage(NULL) και ΚΑΘΕ request σκάει με TypeError — το drush
# bootstrap συνεχίζει να δουλεύει, οπότε φαίνεται σαν "το migrate πέρασε αλλά το pod δεν σηκώνεται".
# Καταγράφουμε μόνο τον ορισμό από τον κώδικα στο last-installed repository· δεν πειράζουμε schema.
# Τρέχει ΜΕΤΑ τα enables, ώστε ο κώδικας να έχει πια όλα τα modules του — αλλιώς πεδίο module
# που δεν έχει ενεργοποιηθεί ακόμα φαίνεται ορφανό. Κριτήριο: ο ορισμός δεν έχει αντίστοιχο
# πεδίο στον κώδικα. Σβήνει μόνο key_value εγγραφή — στήλες και δεδομένα μένουν ανέγγιχτα.
# Αν έστω ένα module απέτυχε να ενεργοποιηθεί, ο κώδικας είναι ελλιπής και το βήμα ΔΕΝ τρέχει.
if [ -n "$ENABLE_FAILED" ]; then
  echo "[migrate] SKIP field definition purge — incomplete module set"
else
echo "[migrate] purge field storage definitions with no counterpart in code"
$DRUSH php:eval '
$repo = \Drupal::service("entity.last_installed_schema.repository");
$efm = \Drupal::service("entity_field.manager");
$removed = [];
foreach (array_keys(\Drupal::entityTypeManager()->getDefinitions()) as $id) {
  $installed = $repo->getLastInstalledFieldStorageDefinitions($id);
  if (!$installed) { continue; }
  $code = $efm->getFieldStorageDefinitions($id);
  $changed = FALSE;
  foreach (array_keys($installed) as $name) {
    if (!isset($code[$name])) {
      unset($installed[$name]);
      $removed[] = "$id.$name";
      $changed = TRUE;
    }
  }
  if ($changed) { $repo->setLastInstalledFieldStorageDefinitions($id, $installed); }
}
if ($removed) { echo "[migrate] purged orphan field definitions: " . implode(", ", $removed) . "\n"; }
else { echo "[migrate] no orphan field definitions\n"; }
' 2>&1 || echo "[migrate] WARN orphan field purge failed (continuing)"
fi

echo "[migrate] check entity keys vs installed field storage definitions"
$DRUSH php:eval '
$repo = \Drupal::service("entity.last_installed_schema.repository");
$efm = \Drupal::service("entity_field.manager");
$repaired = []; $unrepairable = [];
foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $et) {
  if (!$et instanceof \Drupal\Core\Entity\ContentEntityTypeInterface) { continue; }
  $installed = $repo->getLastInstalledFieldStorageDefinitions($id);
  if (!$installed) { continue; }
  $keys = array_filter([$et->getKey("id"), $et->getKey("revision"), $et->getKey("bundle"), $et->getKey("uuid"), $et->getKey("langcode")]);
  $missing = array_diff($keys, array_keys($installed));
  if (!$missing) { continue; }
  $code = $efm->getFieldStorageDefinitions($id);
  foreach ($missing as $name) {
    if (isset($code[$name])) {
      $repo->setLastInstalledFieldStorageDefinition($code[$name]);
      $repaired[] = "$id.$name";
    }
    else {
      $unrepairable[] = "$id.$name";
    }
  }
}
if ($repaired) { echo "[migrate] repaired missing key definitions: " . implode(", ", $repaired) . "\n"; }
if ($unrepairable) { echo "[migrate] ERROR key without definition in code: " . implode(", ", $unrepairable) . "\n"; }
if (!$repaired && !$unrepairable) { echo "[migrate] entity keys OK\n"; }
' 2>&1 || echo "[migrate] WARN entity key check failed (continuing)"
$DRUSH cr >/dev/null 2>&1 || true

# Χτίζει όλα τα table mappings offline — ό,τι θα έσκαγε σε HTTP request σκάει εδώ, με όνομα entity type.
echo "[migrate] smoke: build table mappings"
$DRUSH php:eval '
$errors = [];
foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $et) {
  if (!$et instanceof \Drupal\Core\Entity\ContentEntityTypeInterface) { continue; }
  try {
    $storage = \Drupal::entityTypeManager()->getStorage($id);
    if (!$storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage) { continue; }
    $mapping = $storage->getTableMapping();
    foreach ($mapping->getTableNames() as $table) { $mapping->getAllColumns($table); }
  }
  catch (\Throwable $e) {
    $errors[] = $id . " -> " . $e->getMessage();
  }
}
if ($errors) { echo "[migrate] ERROR broken table mapping:\n  " . implode("\n  ", $errors) . "\n"; }
else { echo "[migrate] table mappings OK\n"; }
' 2>&1 || echo "[migrate] WARN table mapping smoke failed (continuing)"

echo "[migrate] install unicorn_basic_theme + switch default theme to claro + uninstall legacy themes"
$DRUSH php:eval '
$cf = \Drupal::configFactory()->getEditable("system.theme");
if ($cf->get("default") !== "claro") {
  $cf->set("default", "claro")->save();
}
$installer = \Drupal::service("theme_installer");
// Το unicorn_login_page ζητάει το unicorn_basic_theme για τα blocks της σελίδας login.
$installer->install(["unicorn_basic_theme"]);
$legacy = ["liberal_stocks_mobile", "liberal_theme_amp", "liberal_admin_theme", "liberal_theme"];
foreach ($legacy as $theme) {
  try { $installer->uninstall([$theme]); } catch (\Throwable $e) {}
}
' >/dev/null 2>&1 || true

echo "[migrate] import jsonapi_extras configs"
$DRUSH php:eval '
use Symfony\Component\Yaml\Yaml;
$dir = "/opt/drupal/config/sync";
$created = 0; $updated = 0; $failed = 0;
// jsonapi_extras ConfigSubscriber triggers an in-process router rebuild on every save;
// under dev assertions that can throw "container was serialized" and abort the whole loop.
// The config data is persisted BEFORE the subscriber runs, so each write survives — we
// catch per-item and let the separate-process drush cr/router rebuild below finish the job.
foreach (glob("$dir/jsonapi_resource_config.*.yml") as $file) {
  $cfg = Yaml::parseFile($file);
  if (empty($cfg["id"])) continue;
  unset($cfg["_core"]);
  try {
    $storage = \Drupal::entityTypeManager()->getStorage("jsonapi_resource_config");
    $entity = $storage->load($cfg["id"]);
    if ($entity) {
      foreach ($cfg as $k => $v) {
        // Το uuid είναι δεμένο με το περιβάλλον: αν το yml κουβαλάει άλλο από το
        // υπάρχον, το save σκάει με "entity already exists with UUID ...".
        if ($k === "id" || $k === "uuid") { continue; }
        // Merge, όχι replace: modules εκθέτουν πεδία από hook_install (π.χ. το
        // unicorn_client_preview το field_show_to_client) και τα enables τρέχουν
        // ΠΡΙΝ από εδώ, οπότε ένα set() ολόκληρου του resourceFields τα έσβηνε.
        // Το yml υπερισχύει για ό,τι ορίζει· τα υπόλοιπα επιβιώνουν.
        if ($k === "resourceFields") { $v = $v + (array) $entity->get("resourceFields"); }
        $entity->set($k, $v);
      }
      $entity->save();
      $updated++;
    } else {
      $storage->create($cfg)->save();
      $created++;
    }
  } catch (\Throwable $e) {
    $failed++;
  }
}
$settings_file = "$dir/jsonapi_extras.settings.yml";
if (file_exists($settings_file)) {
  $cfg = Yaml::parseFile($settings_file);
  unset($cfg["_core"]);
  try {
    $config = \Drupal::configFactory()->getEditable("jsonapi_extras.settings");
    foreach ($cfg as $k => $v) $config->set($k, $v);
    $config->save();
  } catch (\Throwable $e) {
    $failed++;
  }
}
echo "[migrate] jsonapi_extras: created=$created updated=$updated failed=$failed\n";
' 2>&1 || echo "[migrate] WARN jsonapi import failed (continuing)"

echo "[migrate] import metatag + SEO configs"
$DRUSH php:eval '
use Symfony\Component\Yaml\Yaml;
$dir = "/opt/drupal/config/sync";
$created = 0; $updated = 0; $failed = 0;

foreach (glob("$dir/metatag.metatag_defaults.*.yml") as $file) {
  $cfg = Yaml::parseFile($file);
  if (empty($cfg["id"])) continue;
  unset($cfg["_core"]);
  try {
    $storage = \Drupal::entityTypeManager()->getStorage("metatag_defaults");
    $entity = $storage->load($cfg["id"]);
    if ($entity) {
      foreach ($cfg as $k => $v) {
        if ($k === "id" || $k === "uuid") { continue; }
        // Merge όπως στα jsonapi resourceFields: το yml υπερισχύει για ό,τι ορίζει,
        // τα υπόλοιπα tags του περιβάλλοντος επιβιώνουν αντί να σβηστούν.
        if ($k === "tags") { $v = $v + (array) $entity->get("tags"); }
        $entity->set($k, $v);
      }
      $entity->save();
      $updated++;
    } else {
      $storage->create($cfg)->save();
      $created++;
    }
  } catch (\Throwable $e) {
    $failed++;
  }
}

foreach (["metatag.settings", "robotstxt.settings", "amp.settings", "amp.theme", "amp.analytics.settings"] as $name) {
  $file = "$dir/$name.yml";
  if (!file_exists($file)) { continue; }
  $cfg = Yaml::parseFile($file);
  unset($cfg["_core"]);
  try {
    $config = \Drupal::configFactory()->getEditable($name);
    foreach ($cfg as $k => $v) { $config->set($k, $v); }
    $config->save();
    $updated++;
  } catch (\Throwable $e) {
    $failed++;
  }
}

echo "[migrate] metatag/SEO: created=$created updated=$updated failed=$failed\n";
' 2>&1 || echo "[migrate] WARN metatag import failed (continuing)"

# Fresh processes: rebuild caches + router outside the crashing in-process context above,
# so the imported jsonapi_extras resources register their routes cleanly.
$DRUSH cr >/dev/null 2>&1 || true
$DRUSH ev '\Drupal::service("router.builder")->rebuild();' >/dev/null 2>&1 || true

# OAuth consumer/scope provisioning runs LAST — after updb + cr — so entity definitions
# (e.g. search_api_index) are fully settled. Running it earlier fails on a fresh seed with
# "The search_api_index entity type does not exist".
# OAuth RSA keys: το simple_oauth χρειάζεται public/private key αλλιώς κάθε
# token request σκάει. Παράγονται one-time στο module install (unicorn_api_alterations)
# στο /var/www/html/oauth-keys — αλλά σε νέο pod (image χωρίς persistent volume εκεί)
# λείπουν. Fallback generation ΜΟΝΟ αν δεν υπάρχουν ήδη: αν ο DevOps τα περάσει μέσω
# k8s secret mounted στο ίδιο path, το test -f τα σέβεται και δεν ξαναπαράγει.
# ⚠️ Multi-replica: για σταθερά keys σε όλα τα pods χρειάζεται mounted secret — όχι
# per-pod generation (αλλιώς token από pod A απορρίπτεται από pod B).
echo "[migrate] ensure OAuth RSA keys"
KEY_DIR="${OAUTH_KEY_DIR:-/var/www/html/oauth-keys}"
if [ ! -f "$KEY_DIR/private.key" ] || [ ! -f "$KEY_DIR/public.key" ]; then
  mkdir -p "$KEY_DIR"
  openssl genrsa -out "$KEY_DIR/private.key" 4096 >/dev/null 2>&1 || true
  openssl rsa -in "$KEY_DIR/private.key" -pubout -out "$KEY_DIR/public.key" >/dev/null 2>&1 || true
  echo "[migrate] generated OAuth keys in $KEY_DIR (fallback)"
fi
chown www-data:www-data "$KEY_DIR/private.key" "$KEY_DIR/public.key" 2>/dev/null || true
chmod 600 "$KEY_DIR/private.key" "$KEY_DIR/public.key" 2>/dev/null || true
$DRUSH cset -y simple_oauth.settings public_key "$KEY_DIR/public.key" >/dev/null 2>&1 || true
$DRUSH cset -y simple_oauth.settings private_key "$KEY_DIR/private.key" >/dev/null 2>&1 || true

echo "[migrate] provision FE OAuth consumer + scope"
$DRUSH php:eval '
$ss = \Drupal::entityTypeManager()->getStorage("oauth2_scope");
if (!$ss->load("liberal")) {
  $ss->create(["id"=>"liberal","name"=>"liberal","granularity_id"=>"role","granularity_configuration"=>["role"=>"authenticated"],"grant_types"=>["authorization_code"=>["status"=>true],"refresh_token"=>["status"=>true]]])->save();
}
$cid = getenv("OAUTH_CLIENT_ID"); $secret = getenv("OAUTH_CLIENT_SECRET"); $redirect = getenv("OAUTH_FE_REDIRECT_URI");
if ($cid && $secret) {
  $cs = \Drupal::entityTypeManager()->getStorage("consumer");
  $ids = \Drupal::entityQuery("consumer")->condition("client_id",$cid)->accessCheck(FALSE)->execute();
  $c = $ids ? $cs->load(reset($ids)) : $cs->create(["client_id"=>$cid,"label"=>"Liberal FE"]);
  $c->set("grant_types",[["value"=>"authorization_code"],["value"=>"refresh_token"]]);
  // "liberal" = public site, "dashboard" = FE dashboard. Μόνο όσα υπάρχουν όντως,
  // ώστε να μη σκάει το save αν κάποιο δεν έχει προλάβει να δημιουργηθεί.
  $wanted = array_values(array_filter(["liberal", "dashboard"], fn($id) => $ss->load($id) !== NULL));
  $c->set("scopes", array_map(fn($id) => ["scope_id" => $id], $wanted));
  $c->set("authorization_code_scopes", array_map(fn($id) => ["scope_id" => $id], $wanted));
  // Χωρίς αυτό το dashboard login σταματά στην οθόνη συγκατάθεσης του OAuth (LIBER-4249).
  if ($c->hasField("automatic_authorization")) { $c->set("automatic_authorization", TRUE); }
  if ($redirect) { $c->set("redirect", array_map(fn($u)=>["value"=>trim($u)], explode(",",$redirect))); }
  $c->set("secret",$secret);
  $c->save();
}
' 2>&1 || echo "[migrate] WARN consumer provisioning failed (continuing)"

if [ "${ENV:-}" = "local" ]; then
  echo "[migrate] local env — reset liberaladmin password to admin"
  $DRUSH user:password liberaladmin admin >/dev/null 2>&1 || true
fi

if [ -n "$ENABLE_FAILED" ]; then
  echo "[migrate] FAILED — modules not enabled:$ENABLE_FAILED"
  echo "[migrate] the DB is left as-is; fix the cause and re-run (the script is idempotent)"
  exit 1
fi

echo "[migrate] done"
