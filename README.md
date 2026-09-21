# local-liberal-cms- — snapshot του τοπικού test env «d12»

Αντίγραφο **χωρίς git ιστορικό** του worktree `~/liberal/liberal-cms-d12` (21/09/2026). Ίδιος κώδικας με το
`liberal-cms` (branch `d12/query-optimizer` = develop + LIBER-4480 + LIBER-4647 + LIBER-4668), **συν** τα
τοπικά αρχεία που δεν είναι στο εταιρικό repo:

| Αρχείο | Τι είναι |
|---|---|
| `docker-compose.d12.yml` | override για το d12 stack: drupal `127.0.0.1:8120`, mariadb `3341`, solr `8995`, volumes `d12_clean_db`, `d12_oauth_keys` |
| `.env` | τοπικά credentials (DB, HASH_SALT) — **μην το δημοσιεύσεις** |
| `Dockerfile` / `composer.json` (τροποποιημένα) | προετοιμασία για Drupal core 11.4.6 (LIBER-4648): s3fs 3.11, `allow-plugins symfony/runtime` |

Δεν συμπεριλαμβάνονται: `.git`, `node_modules`, `vendor`, `docker/app/sql/*.sql`.

## Τι δοκιμάστηκε εδώ

- Drupal 11.4 / 12 alpha: το GROUP BY των JSON:API listings **παραμένει** στο 11.4, άλλαξε μόνο το SQL shape
  → το module `unicorn_query_optimizer` χρειάζεται και στο 11.4 (με 2 fixes που μπήκαν στο LIBER-4480).
- Πλήρης προσομοίωση `migrate.sh` σε κλώνο της staging βάσης (`d12_clean_db`).
- LIBER-4668 «env product»: `unicorn_field_registry` — machine names μέσω env vars ώστε ο ίδιος κώδικας να
  τρέχει σε Liberal και Μακεδονικά Νέα.

## Τρέξιμο

```bash
docker compose -p d12 -f docker-compose.yml -f docker-compose.d12.yml build drupal
docker compose -p d12 -f docker-compose.yml -f docker-compose.d12.yml up -d
# API: http://localhost:8120/liberal/unicornapi/   login: liberal@unicorndomain.gr
```
Βάση: Google Drive `liberal-local-20260921.sql.gz` (4,7 GB).

## Σχετικά repos (nandt)

- `liberal-cms` — το κανονικό repo με ιστορικό (develop + master)
- `Liberal-dashboard` — το frontend· το chip «env site» στο login δείχνει σε αυτό το backend (:8120)
