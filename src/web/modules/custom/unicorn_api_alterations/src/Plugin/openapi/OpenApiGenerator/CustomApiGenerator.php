<?php

namespace Drupal\unicorn_api_alterations\Plugin\openapi\OpenApiGenerator;

use Drupal\openapi\Plugin\openapi\OpenApiGeneratorBase;
use Drupal\unicorn_api_alterations\Controller\SectionTemplatesController;

/**
 * Self-documenting OpenAPI generator για όλο το dashboard API σε μία σελίδα.
 *
 * Ομαδοποιημένο (tags: Sections, Home layout, Content, Auth, Helpers), με
 * summary/description/parameters/examples ανά endpoint — ώστε οι frontend να
 * βρίσκουν sort/filter/attributes/fields και να δοκιμάζουν με "Try it out".
 *
 * @OpenApiGenerator(
 *   id = "custom_api",
 *   label = @Translation("Liberal Dashboard API"),
 * )
 */
class CustomApiGenerator extends OpenApiGeneratorBase {

  public function getApiName() {
    return 'Liberal Dashboard API';
  }

  protected function getApiDescription() {
    return 'Όλα τα endpoints του dashboard σε ένα σημείο: Sections (CRUD), Home layout, Content feeds, Auth. Read = flattened (jsonapi_include)· Write = standard JSON:API nested body.';
  }

  protected function getJsonSchema($described_format, $entity_type_id, $bundle_name = NULL) {
    return [];
  }

  #[\Override]
  public function getTags() {
    return [
      ['name' => 'Sections', 'description' => 'Διαχείριση section entities (US 3.1–3.4) — reusable building blocks.'],
      ['name' => 'Home layout', 'description' => 'Η διάταξη των sections στην αρχική ανά variant (State blob, full-replace).'],
      ['name' => 'Content', 'description' => 'Feeds άρθρων για να γεμίζεις τα slots.'],
      ['name' => 'Auth', 'description' => 'OAuth2 login + logout.'],
      ['name' => 'Account', 'description' => 'Ο τρέχων χρήστης: profile, status, roles (labels) και capabilities για gating του UI.'],
      ['name' => 'Features', 'description' => 'Αφιερώματα (oblations) — migration παλιών microsites: λίστα + Zone A (Big-2 Main) + Zone B (List) + metadata. Καθαρό JSON:API.'],
      ['name' => 'Taxonomies', 'description' => 'Categories (US 7.x), Authors (US 5.x), Sources & Series (US 13.x) — λίστες για τα dropdowns και για τους πίνακες του dashboard, με το can_be_deleted flag ανά γραμμή.'],
      ['name' => 'Helpers', 'description' => 'Templates, catalog, AI summary.'],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Καθαρό apiKey auth αντί για το default oauth2 (που έβγαζε χαλασμένο
   * authorize URL). Δύο τρόποι — γεμίζεις όποιον έχεις στο κουμπί "Authorize":
   *   - bearer: OAuth access token (Authorization: Bearer <token>)
   *   - csrf: για writes με cookie-session (X-CSRF-Token από GET /session/token)
   */
  #[\Override]
  public function getSecurityDefinitions() {
    return [
      'bearer' => [
        'type' => 'apiKey',
        'name' => 'Authorization',
        'in' => 'header',
        'description' => 'OAuth Bearer token. Βάλε την τιμή: Bearer <access_token>',
      ],
      'csrf' => [
        'type' => 'apiKey',
        'name' => 'X-CSRF-Token',
        'in' => 'header',
        'description' => 'Μόνο για POST/PATCH/DELETE με cookie-session. Πάρε τιμή από GET /session/token (ενώ είσαι logged in).',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getSecurity() {
    return [
      ['bearer' => []],
      ['csrf' => []],
    ];
  }

  #[\Override]
  public function getDefinitions() {
    return [
      'SectionAttributes' => [
        'type' => 'object',
        'required' => ['internal_name', 'template'],
        'properties' => [
          'internal_name' => ['type' => 'string', 'maxLength' => 50, 'description' => 'Μοναδικό εσωτερικό όνομα (label).'],
          'display_name' => ['type' => 'string', 'maxLength' => 50, 'description' => 'Τίτλος που φαίνεται.'],
          'template' => [
            'type' => 'string',
            'description' => 'Ένα από τα 11 predefined (δες GET /customapi/section-templates).',
            'enum' => array_column(SectionTemplatesController::TEMPLATES, 'id'),
          ],
          'template_label' => [
            'type' => 'string',
            'readOnly' => TRUE,
            'description' => 'Display label derived from the template machine ID.',
          ],
          'link' => ['type' => 'string', 'description' => 'Σύνδεσμος (optional).'],
          'article_slot' => ['type' => 'integer', 'readOnly' => TRUE, 'description' => 'Read-only — το βάζει ο server από το template.'],
          'can_be_deleted' => ['type' => 'boolean', 'readOnly' => TRUE, 'description' => 'Read-only (US 3.4) — `false` όσο το section είναι τοποθετημένο σε κάποια αρχική. Αντικαθιστά το GET /customapi/sections-usage.'],
        ],
      ],
    ];
  }

  #[\Override]
  public function getPaths() {
    $api = \Drupal::config('jsonapi_extras.settings')->get('path_prefix') ?: 'jsonapi';
    $sec = "/$api/section/section";
    $jsonapi_ct = 'application/vnd.api+json';

    // Δυναμικά dropdowns από τη βάση (snapshot στο cache — drush cr για refresh).
    $section_names = $this->dbEnum('section', 'internal_name');
    $categories = $this->categoryMap();

    $value_param = ['name' => 'filter[internal_name][value]', 'in' => 'query', 'type' => 'string', 'description' => 'Ο όρος αναζήτησης (μαζί με το operator).'];
    if ($section_names) {
      $value_param['enum'] = $section_names;
      $value_param['description'] = 'Διάλεξε υπάρχον section (από τη βάση).';
    }

    $term_param = ['name' => 'termUuid', 'in' => 'query', 'type' => 'string', 'description' => 'UUID του taxonomy term (μαζί με filterBy).'];
    if ($categories) {
      $term_param['enum'] = array_keys($categories);
      $term_param['description'] = 'Διάλεξε uuid κατηγορίας από το dropdown (' . count($categories) . ' διαθέσιμες). Name→uuid mapping: GET /' . $api . '/taxonomy_term/category.';
    }

    return [

      // ─────────────── SECTIONS ───────────────
      $sec => [
        'get' => [
          'tags' => ['Sections'],
          'summary' => 'Sections — λίστα, search, sort, paging (US 3.1)',
          'description' => 'Επιστρέφει όλα τα sections. **Read = flattened**: τα attributes είναι κατευθείαν στο `data[i]` (όχι σε `attributes`). Το `id` κάθε item είναι το uuid που χρησιμοποιείς σε Edit/Delete/home-layout.',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 20, 'description' => 'Πόσα ανά σελίδα.'],
            ['name' => 'page[offset]', 'in' => 'query', 'type' => 'integer', 'default' => 0, 'description' => 'Offset για paging.'],
            ['name' => 'sort', 'in' => 'query', 'type' => 'string', 'enum' => ['-changed', 'changed', '-created', 'created', 'internal_name', '-internal_name', 'display_name', '-display_name'], 'description' => 'Ταξινόμηση — `-` = φθίνουσα. Last Updated = `-changed`.'],
            ['name' => 'filter[internal_name][operator]', 'in' => 'query', 'type' => 'string', 'enum' => ['CONTAINS', 'STARTS_WITH', 'ENDS_WITH', '=', '<>'], 'description' => 'Τελεστής αναζήτησης (διάλεξε).'],
            $value_param,
            ['name' => 'filter[template]', 'in' => 'query', 'type' => 'string', 'enum' => ['home', 'apopseis', 'small_1', 'middle_1', 'middle_2', 'middle_3', 'big_1', 'big_2', 'afieroma_1', 'afieroma_2', 'afieroma_3', 'liberal_markets', 'makedonika_nea'], 'description' => 'Φιλτράρισμα ανά template.'],
          ],
          'responses' => [
            '200' => [
              'description' => 'JSON:API λίστα (flattened).',
              'examples' => [$jsonapi_ct => [
                'data' => [[
                  'type' => 'section--section',
                  'id' => '9694680d-2526-42db-a626-038501479cf6',
                  'internal_name' => 'politiki-top',
                  'display_name' => 'Πολιτική',
                  'template' => 'big_1',
                  'template_label' => '9 articles, 3-3-3',
                  'article_slot' => 9,
                  'link' => 'https://liberal.gr/politiki',
                  'created' => '2026-07-15T15:18:47+00:00',
                ]],
                'meta' => ['count' => 1],
              ]],
            ],
          ],
        ],
        'post' => [
          'tags' => ['Sections'],
          'summary' => 'Section — δημιουργία (US 3.2)',
          'description' => "Φτιάχνει το **κέλυφος** ενός section: template + όνομα + link. Τα **άρθρα ΔΕΝ μπαίνουν εδώ** — μπαίνουν όταν τοποθετείς το section στην αρχική (`article_ids` στο POST /customapi/home-layout). Το template είναι predefined (διάλεξε από τα 11). \n\n**Write = standard JSON:API** (nested `data.attributes`). Το `article_slot` το βάζει ο server από το template — μην το στέλνεις. Validation: `internal_name` unique + ≤50, `template` ∈ enum.\n\n"
            . "### Τρεις μορφές, ανάλογα με τα flags του template (US 3.2)\n\n"
            . "Διάβασε τα flags από `GET /customapi/section-templates` — **μην** τα συμπεραίνεις από το label.\n\n"
            . "**1) Κανονικό section** (κανένα flag) — το παράδειγμα του body παρακάτω.\n\n"
            . "**2) Feature Section** (`isFeature: true` → afieroma_1/afieroma_2): στέλνεις `relationships.feature` και **ΟΧΙ** display_name/image/link — τα γεμίζει ο server από το Feature:\n"
            . "```json\n{\n  \"data\": {\n    \"type\": \"section--section\",\n    \"attributes\": { \"internal_name\": \"us-elections-promo\", \"template\": \"afieroma_1\" },\n    \"relationships\": {\n      \"feature\": { \"data\": { \"type\": \"taxonomy_term--oblations\", \"id\": \"<oblations-term-uuid>\" } }\n    }\n  }\n}\n```\n"
            . "Auto-fill στο save: `display_name` ← Feature `name` · `image` ← Feature `field_ob_banner` · `link` ← το URL του Feature.\n\n"
            . "**3) Ad Section** (`isAd: true` → afieroma_3): banner **ή** snippet, τα δύο είναι αμοιβαία αποκλειόμενα, και `link` = το redirect URL:\n"
            . "```json\n{\n  \"data\": {\n    \"type\": \"section--section\",\n    \"attributes\": {\n      \"internal_name\": \"home-top-banner\",\n      \"display_name\": \"Χορηγία\",\n      \"link\": \"https://advertiser.example.com/landing\",\n      \"template\": \"afieroma_3\",\n      \"image_snippet\": \"<div id=\\\"gam-slot\\\"></div>\"\n    }\n  }\n}\n```\n"
            . "Για ανεβασμένη εικόνα αντί για snippet: **παράλειψε** το `image_snippet`, δημιούργησε πρώτα το section και μετά κάνε JSON:API file upload στο `POST /liberal/unicornapi/section/section/{uuid}/image` (`Content-Type: application/octet-stream`, `Content-Disposition: file; filename=\"banner.jpg\"`).",
          'consumes' => [$jsonapi_ct],
          'produces' => [$jsonapi_ct],
          'parameters' => [[
            'name' => 'body', 'in' => 'body', 'required' => TRUE,
            'schema' => [
              'type' => 'object',
              'example' => [
                'data' => [
                  'type' => 'section--section',
                  'attributes' => [
                    'internal_name' => 'politiki-top',
                    'display_name' => 'Πολιτική',
                    'template' => 'big_1',
                    'link' => 'https://liberal.gr/politiki',
                  ],
                ],
              ],
            ],
          ]],
          'responses' => [
            '201' => [
              'description' => 'Δημιουργήθηκε — `data.id` = uuid, `article_slot` derived (big_1 → 9), `can_be_deleted` = true όσο δεν έχει τοποθετηθεί σε αρχική.',
              'examples' => [$jsonapi_ct => [
                'data' => [
                  'type' => 'section--section',
                  'id' => '9694680d-2526-42db-a626-038501479cf6',
                  'drupal_internal__id' => 12,
                  'internal_name' => 'politiki-top',
                  'display_name' => 'Πολιτική',
                  'template' => 'big_1',
                  'link' => 'https://liberal.gr/politiki',
                  'article_slot' => 9,
                  'can_be_deleted' => TRUE,
                  'created' => '2026-08-14T10:15:00+00:00',
                  'changed' => '2026-08-14T10:15:00+00:00',
                ],
              ]],
            ],
            '422' => ['description' => 'Validation error (κενό/διπλό internal_name, >50, άκυρο template).'],
          ],
        ],
      ],

      "$sec/{uuid}" => [
        'get' => [
          'tags' => ['Sections'],
          'summary' => 'Section — ένα (prefill του Edit)',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'uuid', 'in' => 'path', 'type' => 'string', 'required' => TRUE, 'description' => 'Το uuid από τη λίστα.'],
            ['name' => 'include', 'in' => 'query', 'type' => 'string', 'enum' => ['feature'], 'description' => 'Σχέσεις να συμπεριληφθούν (feature sections).'],
          ],
          'responses' => ['200' => ['description' => 'Ένα section (flattened).']],
        ],
        'patch' => [
          'tags' => ['Sections'],
          'summary' => 'Section — επεξεργασία (US 3.3)',
          'description' => 'Στέλνεις μόνο τα πεδία που αλλάζουν + το `id` μέσα στο `data`. Αλλαγή template → ξαναϋπολογίζεται το `article_slot`.',
          'consumes' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'uuid', 'in' => 'path', 'type' => 'string', 'required' => TRUE],
            ['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => [
              'type' => 'object',
              'example' => ['data' => ['type' => 'section--section', 'id' => '<uuid>', 'attributes' => ['display_name' => 'Πολιτική / Κόσμος', 'template' => 'middle_1']]],
            ]],
          ],
          'responses' => ['200' => ['description' => 'Ενημερώθηκε.']],
        ],
        'delete' => [
          'tags' => ['Sections'],
          'summary' => 'Section — διαγραφή (US 3.4)',
          'description' => 'Επιτρέπεται μόνο αν το section **δεν χρησιμοποιείται** (δες GET /customapi/sections-usage). ',
          'parameters' => [['name' => 'uuid', 'in' => 'path', 'type' => 'string', 'required' => TRUE]],
          'responses' => ['204' => ['description' => 'Διαγράφηκε.']],
        ],
      ],

      // ─────────────── HELPERS (sections) ───────────────
      '/customapi/section-templates' => [
        'get' => [
          'tags' => ['Helpers'],
          'summary' => 'Templates — τα 11 predefined (dropdown)',
          'description' => 'Τα templates **ΔΕΝ δημιουργούνται** — είναι 11 σταθερά layouts που τα renderάρει το frontend. Εδώ παίρνεις τη λίστα (label + article slots + feature/preview) για το dropdown. Ο editor απλά **διαλέγει** template όταν φτιάχνει section.',
          'responses' => ['200' => ['description' => 'Array templates.', 'examples' => ['application/json' => [
            ['id' => 'big_1', 'label' => 'Big-1', 'articleSlots' => 9, 'isFeature' => FALSE, 'frontImage' => FALSE],
            ['id' => 'afieroma_1', 'label' => 'Αφιέρωμα 1', 'articleSlots' => 3, 'isFeature' => TRUE, 'frontImage' => TRUE],
          ]]]],
        ],
      ],
      '/customapi/sections-usage' => [
        'get' => [
          'tags' => ['Helpers'],
          'summary' => 'Delete-enabled flags (in-use ανά section) — legacy',
          'description' => 'Μία κλήση για όλο τον πίνακα. `true` = in use → Delete disabled.

**Δεν χρειάζεται πια:** το `section--section` επιστρέφει `can_be_deleted` μέσα στο ίδιο GET, με **αντίστροφη** (και ενιαία) σημασιολογία: `can_be_deleted: true` = διαγράφεται. Το endpoint μένει για συμβατότητα.',
          'responses' => ['200' => ['description' => 'Map uuid → bool.', 'examples' => ['application/json' => ['9694680d-2526-42db-a626-038501479cf6' => FALSE]]]],
        ],
      ],

      '/customapi/metatag-route' => [
        'get' => [
          'tags' => ['SEO'],
          'summary' => 'Metatags & JSON-LD για μια διαδρομή',
          'description' => "Επιστρέφει όλα τα `<head>` metadata που πρέπει να βγάλει ο Next.js για ένα path: title/description/canonical/robots, OpenGraph, Twitter Cards και το **structured data (JSON-LD)**.\n\n"
            . "Δώσε το **path όπως το βλέπει ο χρήστης** (alias ή `/node/{nid}`). Δέχεται και τα protected metatag defaults ως σκέτο όνομα: `403`, `404`, `front`, `global`.\n\n"
            . "**Τρία ξεχωριστά payloads:**\n"
            . "- `tags` → επίπεδη λίστα από `<meta>`/`<link>` στοιχεία· render το καθένα αυτούσιο\n"
            . "- `jsonld` → **έτοιμο object**, όχι string. Βάλ' το σε `<script type=\"application/ld+json\">` με `JSON.stringify`. Είναι `null` όταν η διαδρομή δεν έχει schema.org δεδομένα (π.χ. σελίδες σφάλματος).\n"
            . "- `breadcrumb` → η αλυσίδα κατηγοριών, από τη ρίζα προς το φύλλο, για την ορατή μπάρα **και** το `BreadcrumbList` JSON-LD\n\n"
            . "⚠️ Το `jsonld` **δεν** περιέχεται στα `tags` — παράγεται ξεχωριστά από το `schema_metatag`. Μην ψάχνεις structured data μέσα στα `tags`.\n\n"
            . "**Το `@graph` έχει τρεις κόμβους.** Ο `Organization` και ο `WebSite` είναι σταθεροί σε κάθε σελίδα, με σταθερό `@id`· ο κόμβος της σελίδας τους αναφέρει μέσω `@id` αντί να τους επαναλαμβάνει. Μη γράψεις κώδικα που περιμένει το `publisher` inline — είναι `{\"@id\": \"…/#organization\"}`.\n\n"
            . "Ο `WebSite` παίρνει `potentialAction` (sitelinks search box) **μόνο** όταν έχει οριστεί το `SEO_SEARCH_PATH` — π.χ. `/search?q={search_term_string}`. Χωρίς αυτό ο κόμβος βγαίνει χωρίς το κλειδί, που είναι έγκυρο· δεν δηλώνουμε αναζήτηση που δεν απαντά, γιατί η Google δοκιμάζει το target.\n\n"
            . "**Το `breadcrumb`** δίνει **σχετικές** διαδρομές, όπως και το `path` του αιτήματος — βάλε εσύ το origin. Για το `BreadcrumbList` το `item` πρέπει να είναι απόλυτο. Είναι κενός πίνακας όταν το άρθρο δεν έχει κατηγορία και σε σελίδες σφάλματος· **δεν** είναι σφάλμα. Οι σελίδες κατηγορίας επιστρέφουν τη δική τους ιεραρχία. 45 από τους 127 όρους είναι ένθετοι, οπότε ο πίνακας μπορεί να έχει περισσότερα από ένα επίπεδα — και το alias του άρθρου κουβαλά μόνο την κατηγορία-φύλλο, άρα η αλυσίδα δεν βγαίνει από το URL.",
          'parameters' => [[
            'name' => 'path',
            'in' => 'query',
            'required' => TRUE,
            'type' => 'string',
            'description' => 'Το path με αρχική `/` (π.χ. `/politiki/tramp-kai-amoralismos`), ή ένα από τα `403`, `404`, `front`, `global`.',
          ]],
          'responses' => [
            '200' => ['description' => "Πραγματική απάντηση για το άρθρο 625592 (21 tags, εδώ 8). Το host αλλάζει ανά περιβάλλον.\n\n"
              . "Τα `article:*` ακολουθούν τα αντίστοιχα schema tags: το `article:modified_time` διαβάζει **το ίδιο πεδίο** με το `dateModified` του JSON-LD και το `lastmod` του sitemap. Το `article:tag` βγαίνει **ένα στοιχείο ανά tag** — άρθρο με δύο tags δίνει δύο `<meta>`. Άρθρο χωρίς συντακτική ημερομηνία δεν βγάζει καθόλου `article:modified_time`, όπως και δεν βγάζει `dateModified`.\n\n"
              . "Video Article επιστρέφει `@type: VideoObject` με `duration`/`contentUrl`/`embedUrl`/`about`/`genre`/`inLanguage` και **χωρίς** `dateModified`/`headline`/`articleSection`/`articleBody`.\n\n"
              . "Το παράδειγμα έχει **επίπεδη** κατηγορία, οπότε το `breadcrumb` έχει ένα επίπεδο. Για **ένθετη**, δες το άρθρο 625478 (`/epiheiriseis/metlen-ti-fernei-deal-orosimo-gia-gallio-nea-yperaxia-blepoyn-oi-koryfaioi-oikoi`), που επιστρέφει `ΟΙΚΟΝΟΜΙΑ → Επιχειρήσεις`.", 'examples' => ['application/json' => [
              'path' => '/politiki/st-lygeros-apantisi-sto-libelo-toy-giorgoy-karampelia',
              'route' => 'entity.node.canonical',
              'entity' => ['type' => 'node', 'bundle' => 'article_liberal', 'id' => 625592, 'uuid' => '6a6d2171-18e1-452c-b18e-cc1d063bca71'],
              'breadcrumb' => [
                ['id' => 7, 'name' => 'ΠΟΛΙΤΙΚΗ', 'url' => '/katigories/politiki'],
              ],
              'tags' => [
                ['tag' => 'meta', 'attributes' => ['name' => 'title', 'content' => 'Στ. Λυγερός: Απάντηση στον λίβελο του Γιώργου Καραμπελιά | Liberal.gr']],
                ['tag' => 'meta', 'attributes' => ['name' => 'description', 'content' => 'Δεν περιμένω από τον Γιώργο Καραμπελιά να αντιληφθεί τη διάκριση…']],
                ['tag' => 'link', 'attributes' => ['rel' => 'canonical', 'href' => 'https://www.liberal.gr/politiki/st-lygeros-apantisi-sto-libelo-toy-giorgoy-karampelia']],
                ['tag' => 'meta', 'attributes' => ['name' => 'robots', 'content' => 'index, follow']],
                ['tag' => 'meta', 'attributes' => ['property' => 'article:publisher', 'content' => 'https://www.facebook.com/liberalgr']],
                ['tag' => 'meta', 'attributes' => ['name' => 'twitter:site', 'content' => '@LiberalGr']],
                ['tag' => 'meta', 'attributes' => ['property' => 'article:published_time', 'content' => '2026-07-30T09:00:09+0300']],
                ['tag' => 'meta', 'attributes' => ['property' => 'article:section', 'content' => 'ΠΟΛΙΤΙΚΗ']],
                ['tag' => 'meta', 'attributes' => ['property' => 'article:tag', 'content' => 'ΣΤΑΥΡΟΣ ΛΥΓΕΡΟΣ']],
                ['tag' => 'meta', 'attributes' => ['property' => 'article:tag', 'content' => 'ΓΙΩΡΓΟΣ ΚΑΡΑΜΠΕΛΙΑΣ']],
              ],
              'jsonld' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                  [
                    '@type' => 'Organization',
                    '@id' => 'https://www.liberal.gr/#organization',
                    'name' => 'Liberal',
                    'url' => 'https://www.liberal.gr/',
                    'sameAs' => ['https://www.facebook.com/liberalgr/', 'https://twitter.com/LiberalGr'],
                    'logo' => ['@type' => 'ImageObject', 'url' => 'https://www.liberal.gr/themes/custom/liberal_theme/img/liberal_logo.svg', 'width' => '251', 'height' => '150'],
                  ],
                  [
                    '@type' => 'WebSite',
                    '@id' => 'https://www.liberal.gr/#website',
                    'url' => 'https://www.liberal.gr/',
                    'name' => 'Liberal.gr',
                    'publisher' => ['@id' => 'https://www.liberal.gr/#organization'],
                  ],
                  [
                    '@type' => 'NewsArticle',
                    'headline' => 'Στ. Λυγερός: Απάντηση στον λίβελο του Γιώργου Καραμπελιά',
                    'name' => 'Στ. Λυγερός: Απάντηση στον λίβελο του Γιώργου Καραμπελιά',
                    'description' => 'Δεν περιμένω από τον Γιώργο Καραμπελιά να αντιληφθεί τη διάκριση…',
                    'image' => ['@type' => 'ImageObject', 'url' => 'https://static.liberal.gr/2026-07/lygeros.jpg', 'width' => '1718', 'height' => '1004'],
                    'datePublished' => '2026-07-30T09:00:09+0300',
                    'author' => ['@type' => 'Person', 'name' => 'Σταύρος Λυγερός', 'url' => 'https://www.liberal.gr/arthrografoi/stayros-lygeros'],
                    'publisher' => ['@id' => 'https://www.liberal.gr/#organization'],
                    'mainEntityOfPage' => ['@type' => 'Webpage', '@id' => 'https://www.liberal.gr/politiki/st-lygeros-apantisi-sto-libelo-toy-giorgoy-karampelia'],
                    'keywords' => 'ΣΤΑΥΡΟΣ ΛΥΓΕΡΟΣ,ΓΙΩΡΓΟΣ ΚΑΡΑΜΠΕΛΙΑΣ',
                    'articleSection' => 'ΠΟΛΙΤΙΚΗ',
                    'articleBody' => 'Δεν περιμένω από τον Γιώργο Καραμπελιά…',
                  ],
                ],
              ],
            ]]],
            '400' => ['description' => 'Λείπει το `path`, ή δεν ξεκινά με `/`.', 'examples' => ['application/json' => ['error' => 'Path should start with /.']]],
            '404' => ['description' => 'Η διαδρομή δεν αντιστοιχεί σε route, ή δεν έχει metatags.', 'examples' => ['application/json' => ['error' => 'No route found for this path.']]],
          ],
        ],
      ],

      // ─────────────── HOME LAYOUT ───────────────
      '/customapi/advertising-tools/admin' => [
        'get' => [
          'tags' => ['Advertising'],
          'summary' => 'Προωθούμενα άρθρα — λίστα',
          'description' => "Οι εγγραφές προώθησης, με τον τίτλο του άρθρου joined. Φιλτράρεται και ταξινομείται.\n\n"
            . "Τα **ενεργά** (`state: true`) είναι ο πίνακας «Προωθούμενα»· τα ανενεργά είναι το «Ιστορικό». Ένα φίλτρο, δύο πίνακες στο UI.",
          'parameters' => [
            ['name' => 'entity_id', 'in' => 'query', 'type' => 'integer', 'description' => 'Node ID.'],
            ['name' => 'state', 'in' => 'query', 'type' => 'boolean', 'description' => '`true` = προωθούμενα, `false` = ιστορικό.'],
            ['name' => 'node_title', 'in' => 'query', 'type' => 'string', 'description' => 'Αναζήτηση στον τίτλο.'],
            ['name' => 'sort', 'in' => 'query', 'type' => 'string', 'default' => 'id', 'enum' => ['history_date', 'promoted_date', 'id']],
            ['name' => 'direction', 'in' => 'query', 'type' => 'string', 'default' => 'DESC', 'enum' => ['ASC', 'DESC']],
            ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'default' => 0],
            ['name' => 'per_page', 'in' => 'query', 'type' => 'integer', 'default' => 25, 'description' => '1–100.'],
          ],
          'responses' => ['200' => ['description' => 'Πραγματική απόκριση του endpoint. Το `ctr` υπολογίζεται ως `clicks / impressions * 100` — 37/412 = 8.98%.', 'examples' => ['application/json' => [
            'data' => [[
              'entity_id' => 625623,
              'title' => 'Θ. Πλεύρης: Συνάντηση με την πρέσβη του Μπαγκλαντές…',
              'impressions' => 412,
              'target_impressions' => 1000,
              'clicks' => 37,
              'ctr' => '8.98%',
              'priority' => 8,
              'state' => TRUE,
              'start_date' => '2026-08-12T23:53:20+0300',
              'end_date' => '2026-09-11T23:53:20+0300',
              'promoted_date' => '2026-08-12T23:53:20+0300',
              'history_date' => NULL,
            ]],
            'pager' => [
              'total' => 1, 'count' => 1, 'items_per_page' => 25, 'current_page' => 0,
              'total_pages' => 1, 'has_next_page' => FALSE, 'has_previous_page' => FALSE,
              'previous_page' => NULL, 'next_page' => NULL, 'last_page' => 0,
            ],
          ]]]],
        ],
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'Προωθούμενα άρθρα — νέα εγγραφή',
          'description' => "Ξεκινά την προώθηση ενός άρθρου. **Όλα τα πεδία υποχρεωτικά**, το `state` πρέπει να είναι `true`. Το `promoted_date` μπαίνει αυτόματα.",
          'consumes' => ['application/json'],
          'parameters' => [['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => [
            'type' => 'object',
            'required' => ['entity_id', 'target_impressions', 'start_date', 'end_date', 'state', 'priority'],
            'example' => [
              'entity_id' => 625623,
              'target_impressions' => 1000,
              'start_date' => '2026-09-01T00:00:00+00:00',
              'end_date' => '2026-09-30T00:00:00+00:00',
              'state' => TRUE,
              'priority' => 8,
            ],
          ]]],
          'responses' => ['201' => ['description' => 'Δημιουργήθηκε. Κενό σώμα.']],
        ],
      ],
      '/customapi/advertising-tools/admin/{entityId}' => [
        'get' => [
          'tags' => ['Advertising'],
          'summary' => 'Προωθούμενο άρθρο — μία εγγραφή',
          'description' => '⚠️ Αν το `entityId` δεν υπάρχει επιστρέφει **κενό object** `{}` με 200, όχι 404.',
          'parameters' => [['name' => 'entityId', 'in' => 'path', 'required' => TRUE, 'type' => 'integer']],
          'responses' => ['200' => ['description' => 'Η εγγραφή, ή `{}`.']],
        ],
        'patch' => [
          'tags' => ['Advertising'],
          'summary' => 'Προωθούμενο άρθρο — μερική ενημέρωση',
          'description' => "Γράφονται μόνο τα πεδία που στέλνεις. Το `entity_id` του σώματος αγνοείται — μετράει αυτό του path.\n\n"
            . "Το `promoted_date` ανανεώνεται **μόνο** όταν το `state` περνά από `false` σε `true`· αλλιώς μένει ως έχει.",
          'consumes' => ['application/json'],
          'parameters' => [
            ['name' => 'entityId', 'in' => 'path', 'required' => TRUE, 'type' => 'integer'],
            ['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => ['type' => 'object', 'example' => ['target_impressions' => 2500, 'priority' => 10]]],
          ],
          'responses' => ['200' => ['description' => 'Ενημερώθηκε.'], '400' => ['description' => 'Το `entityId` δεν υπάρχει.']],
        ],
        'delete' => [
          'tags' => ['Advertising'],
          'summary' => 'Προωθούμενο άρθρο — διαγραφή εγγραφής',
          'description' => 'Σβήνει την εγγραφή προώθησης. Το άρθρο δεν θίγεται.',
          'parameters' => [['name' => 'entityId', 'in' => 'path', 'required' => TRUE, 'type' => 'integer']],
          'responses' => ['200' => ['description' => 'Διαγράφηκε.'], '400' => ['description' => 'Δεν διαγράφηκε τίποτα.']],
        ],
      ],
      '/customapi/advertising-tools/track/impressions' => [
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'Tracking — impressions (batch, ασύγχρονο)',
          'description' => "**Δημόσιο.** Μπαίνει σε ουρά· ο μετρητής **δεν** ανεβαίνει αμέσως. Την ουρά την αδειάζει το cron με `advertisingtools:consume_adtracker`.",
          'consumes' => ['application/json'],
          'parameters' => [['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => ['type' => 'object', 'example' => ['nids' => [625623, 625478]]]]],
          'responses' => ['200' => ['description' => 'Μπήκε στην ουρά.']],
        ],
      ],
      '/customapi/advertising-tools/track/clicks/{nid}' => [
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'Tracking — click (σύγχρονο)',
          'description' => '**Δημόσιο.** Σε αντίθεση με τα impressions, γράφεται **απευθείας** στην εγγραφή, χωρίς ουρά.',
          'parameters' => [['name' => 'nid', 'in' => 'path', 'required' => TRUE, 'type' => 'integer']],
          'responses' => ['200' => ['description' => 'Καταγράφηκε.']],
        ],
      ],
      '/customapi/advertising-tools/track/views/{nid}' => [
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'Tracking — page view (ασύγχρονο)',
          'description' => "**Δημόσιο.** Ουρά· την αδειάζει το `advertisingtools:consume_statistics` στο `node_counter` (daycount/totalcount).",
          'parameters' => [['name' => 'nid', 'in' => 'path', 'required' => TRUE, 'type' => 'integer']],
          'responses' => ['200' => ['description' => 'Μπήκε στην ουρά.']],
        ],
      ],
      '/customapi/advertising-tools/amp/track/impressions' => [
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'Tracking AMP — impressions',
          'description' => "Ίδια συμπεριφορά με το κανονικό, με δύο διαφορές: το `nid` έρχεται στο **σώμα**, και προστίθενται CORS headers όταν το `Origin` ταιριάζει με το `AMP_PUBLISHER_ID`. Χωρίς ορισμένη τη μεταβλητή, το CORS είναι κλειστό.",
          'consumes' => ['application/json'],
          'parameters' => [['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => ['type' => 'object', 'example' => ['nids' => [625623]]]]],
          'responses' => ['200' => ['description' => 'Μπήκε στην ουρά.'], '204' => ['description' => 'OPTIONS preflight.']],
        ],
      ],
      '/customapi/advertising-tools/amp/track/clicks' => [
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'Tracking AMP — click',
          'description' => 'Σύγχρονο, όπως το κανονικό click. Το `nid` στο σώμα, CORS όπως παραπάνω.',
          'consumes' => ['application/json'],
          'parameters' => [['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => ['type' => 'object', 'example' => ['nid' => 625623]]]],
          'responses' => ['200' => ['description' => 'Καταγράφηκε.'], '204' => ['description' => 'OPTIONS preflight.']],
        ],
      ],
      '/{jsonapi}/advertising-tools/read-more/{node}' => [
        'get' => [
          'tags' => ['Advertising'],
          'summary' => 'Read More — η λίστα με τα προωθούμενα σφηνωμένα μέσα',
          'description' => "**Δημόσιο.** Επιστρέφει τα `field_homepage_bullets` του άρθρου ως πλήρη `node--article_liberal` resources, με τα προωθούμενα άρθρα παρεμβεβλημένα.\n\n"
            . "⚠️ Το `{jsonapi}` είναι το **path prefix του περιβάλλοντος**, όχι σταθερό. Το route δηλώνεται ως `/%jsonapi%/…` και σήμερα λύνεται σε **`/unicornapi/`**. Μην κωδικοποιήσεις `liberalapi` — άλλαξε με το LIBER-4323.\n\n"
            . "**Πόσα και πού:** 1 προωθούμενο αν η λίστα έχει < 4 στοιχεία, αλλιώς 2. Οι θέσεις είναι σταθερές. Αν η λίστα είναι κενή, μπαίνει μόνο ένα, στο τέλος.\n\n"
            . "**Ποια:** κλήρωση με βάρη — το `priority` (1–10) ορίζει το μέγεθος της «φέτας», δεν εγγυάται νίκη. Το ίδιο άρθρο δεν μπορεί να πιάσει δύο θέσεις.\n\n"
            . "Το `meta.promoted` λέει ποια nids μπήκαν ως προωθούμενα, ώστε να μπορείς να τα σημάνεις οπτικά.",
          'parameters' => [
            ['name' => 'jsonapi', 'in' => 'path', 'required' => TRUE, 'type' => 'string', 'default' => 'unicornapi', 'description' => 'Το JSON:API prefix του περιβάλλοντος.'],
            ['name' => 'node', 'in' => 'path', 'required' => TRUE, 'type' => 'integer', 'description' => 'Το άρθρο που διαβάζεται.'],
          ],
          'responses' => ['200' => ['description' => 'JSON:API document με `meta.promoted`.', 'examples' => ['application/json' => [
            'data' => ['<node--article_liberal resources, με τη σειρά εμφάνισης>'],
            'meta' => ['promoted' => [625623]],
          ]]]],
        ],
      ],
      '/customapi/promotional-banner' => [
        'get' => [
          'tags' => ['Advertising'],
          'summary' => 'News Feed Ad — οι ρυθμίσεις του banner',
          'description' => "Ένα banner για όλο το site: εικόνα, link, on/off. Το module **δεν** αποφασίζει αν εμφανίζεται — αυτό είναι δουλειά όποιου καταναλώνει το API.",
          'responses' => ['200' => ['description' => 'Πραγματική απόκριση με το banner μη ρυθμισμένο — έτσι φαίνεται η κενή κατάσταση.', 'examples' => ['application/json' => [
            'image_url' => NULL, 'status' => FALSE, 'target_url' => '', 'width' => '', 'height' => '',
          ]]]],
        ],
        'post' => [
          'tags' => ['Advertising'],
          'summary' => 'News Feed Ad — ενημέρωση ρυθμίσεων',
          'description' => "`multipart/form-data`, **όχι** JSON. Αλλάζουν μόνο τα πεδία που στέλνεις· ό,τι παραλείψεις κρατά την προηγούμενη τιμή.\n\n"
            . "Νέα εικόνα σβήνει πάντα την προηγούμενη. Ό,τι δεν είναι SVG μετατρέπεται σε webp και κλιμακώνεται σε πλάτος 196px· τα SVG κρατούν τις δικές τους διαστάσεις.\n\n"
            . "Επιτρεπτά: png, jpg, jpeg, svg, webp. Μέγιστο 20MB, με έλεγχο στην κατάληξη.",
          'consumes' => ['multipart/form-data'],
          'parameters' => [
            ['name' => 'status', 'in' => 'formData', 'type' => 'string', 'description' => 'Δέχεται `true`/`false`, `1`/`0`, `"true"`/`"false"`.'],
            ['name' => 'target_url', 'in' => 'formData', 'type' => 'string', 'description' => 'Έγκυρο URL.'],
            ['name' => 'file', 'in' => 'formData', 'type' => 'file', 'description' => 'Η νέα εικόνα.'],
          ],
          'responses' => ['200' => ['description' => 'Αποθηκεύτηκε.']],
        ],
      ],
      '/customapi/promotional-banner/image' => [
        'delete' => [
          'tags' => ['Advertising'],
          'summary' => 'News Feed Ad — διαγραφή μόνο της εικόνας',
          'description' => 'Σβήνει την αποθηκευμένη εικόνα και την καθαρίζει από τις ρυθμίσεις. Το `status` και το `target_url` **δεν** θίγονται.',
          'responses' => ['200' => ['description' => 'Διαγράφηκε.']],
        ],
      ],
      '/customapi/home-layout' => [
        'get' => [
          'tags' => ['Home layout'],
          'summary' => 'Home layout — διάβασε τη διάταξη',
          'description' => "Η διάταξη των sections στην αρχική ενός variant, **σε σειρά εμφάνισης**. Είναι State blob (JSON), όχι entity.\n\n"
            . "### Συμβατότητα — τίποτα δεν σπάει\n\n"
            . "**Κάθε** section, composite ή όχι, εξακολουθεί να φέρει `article_ids`: επίπεδη λίστα σε σειρά εμφάνισης. Για τα composite είναι η **συνένωση** των `subsections` με τη σειρά τους. Όποιος καταναλωτής διαβάζει σήμερα `article_ids` συνεχίζει να δουλεύει χωρίς καμία αλλαγή.\n\n"
            . "Τα `subsections` είναι **προσθήκη**: δίνουν την ομαδοποίηση και το πλήθος slots ανά ομάδα, ώστε ο editor να βλέπει ξεχωριστό μετρητή και ο renderer να μη χρειάζεται να κόβει τη λίστα με βάση τη θέση.\n\n"
            . "Ισχύει και για τα υπάρχοντα δεδομένα: layout αποθηκευμένο με επίπεδη λίστα διαβάζεται κανονικά και μοιράζεται στις ομάδες, χωρίς migration.\n\n"
            . "### 🔴 Γιατί να στείλεις `subsections` και όχι επίπεδη λίστα\n\n"
            . "Η επίπεδη λίστα **δεν κουβαλάει πού τελειώνει η μία ομάδα**. Ο μόνος τρόπος να μοιραστεί είναι με βάση τα slots: πρώτα 5 στο `home_lm`, μετά 6 στο `top_stories`. Αυτό γεννά δύο πραγματικά προβλήματα.\n\n"
            . "**1) Μετατόπιση όταν μια ομάδα δεν είναι γεμάτη.** Ο editor βάζει 3 στο Home LM και 6 στο Top Stories:\n\n"
            . "```\nΕπίπεδη [HM1,HM2,HM3,TS1,TS2,TS3,TS4,TS5,TS6]\n  → home_lm     [HM1, HM2, HM3, TS1, TS2]   ← δύο Top Stories ανέβηκαν\n  → top_stories [TS3, TS4, TS5, TS6]        ← έχασε δύο\n\nΜε subsections\n  → home_lm     [HM1, HM2, HM3]\n  → top_stories [TS1 … TS6]\n```\n\n"
            . "**2) Απώλεια άρθρων όταν το σύνολο ξεπερνά τα slots.** Δεκατρία άρθρα σε επίπεδη λίστα:\n\n"
            . "```\nΕπίπεδη (13)      → home_lm 5/5 · top_stories 6/6 → ΣΥΝΟΛΟ 11   ← 2 ΧΑΘΗΚΑΝ\nΜε subsections (7+6) → home_lm 7/5 · top_stories 6/6 → ΣΥΝΟΛΟ 13   ← όλα εκεί\n```\n\n"
            . "Τα δύο σχήματα **ταυτίζονται μόνο όταν κάθε ομάδα είναι ακριβώς γεμάτη**. Επειδή τα slots είναι ένδειξη και όχι όριο, αυτό δεν είναι εγγυημένο.\n\n"
            . "Ισχύει και για το **Home**, όπου είναι σοβαρότερο: πέντε ομάδες και 23 slots, οπότε μία μετατόπιση παρασύρει όλες τις επόμενες.\n\n"
            . "### Ο κανόνας σε μία γραμμή\n\n"
            . "Κάθε section έχει **`id`** και **`type`**. Αν `type == \"composite\"` δείχνεις τα `subsections`, αλλιώς τα `article_ids`. Στο `POST` επιστρέφεις το `id` όπως το έλαβες. Καμία ειδική περίπτωση.\n\n"
            . "Το `id` είναι το `uuid` όπου υπάρχει Section entity, και το `key` για το Home που ζει στον κώδικα και δεν έχει εγγραφή στη βάση. Τα `uuid`/`key` παραμένουν χωριστά για όποιον χρειάζεται τη διάκριση — π.χ. για να φορτώσει το Section entity.\n\n"
            . "### Τρία είδη στοιχείων στο `sections`\n\n"
            . "**1) Composite** (`type: \"composite\"`) — το **Home**. Έχει `key` αντί για `uuid` και `subsections`. Επιστρέφεται **πάντα πρώτο** και **πάντα υπάρχει**, ακόμη κι αν δεν έχει σωθεί ποτέ τίποτα. Τα sub-sections του είναι σταθερά: **Main 3, Secondary 3, Black Box 1, Blue Articles 3, Top Stories 13** — σύνολο 23.\n\n"
            . "**2) Section** (`type: \"section\"`) — κανονικό placement με `uuid`, εμπλουτισμένο από το Section entity. Αν το entity διαγράφηκε, φέρει `missing: true`.\n\n"
            . "**3) Composite placement** — placement του οποίου το **template** έχει σταθερά sub-sections. Σήμερα μόνο το **`liberal_markets`**: `Home LM` 5 + `Top Stories` 6 = 11. Έρχεται με `type: \"composite\"` και `subsections` αντί για `article_ids`, κρατάει το `uuid` του, και είναι **`deletable: false`** αλλά **`reorderable: true`** — μετακινείται ανάμεσα στα υπόλοιπα sections, δεν διαγράφεται.\n\n"
            . "### Flags — μη τα συμπεραίνεις μόνος σου\n\n"
            . "`deletable` και `reorderable` λένε στο frontend τι UI να δείξει. Το Home έχει **και τα δύο `false`**: χωρίς κουμπί διαγραφής, χωρίς βελάκια πάνω/κάτω.\n\n"
            . "### Μετρητής slots\n\n"
            . "`article_slot` = το **αναμενόμενο** πλήθος, όχι όριο. Ο μετρητής `used/total` βγαίνει από `article_ids.length` έναντι `article_slot` — κοκκινίζει σε υπέρβαση, αλλά το save περνάει κανονικά. Δεν στέλνεται ξεχωριστό flag υπέρβασης.\n\n"
            . "**Access:** το `markets` variant απαιτεί `access liberal markets dashboard` (Admin/Editor, ΟΧΙ Commercial/Client).",
          'parameters' => [['name' => 'variant', 'in' => 'query', 'type' => 'string', 'default' => 'liberal', 'enum' => ['liberal', 'markets'], 'description' => 'Ποια αρχική.']],
          'responses' => ['200' => ['description' => 'Το layout — Home πρώτο, μετά τα κανονικά sections.', 'examples' => ['application/json' => [
            'variant' => 'liberal',
            'sections' => [
              [
                'id' => 'home',
                'key' => 'home',
                'type' => 'composite',
                'internal_name' => 'home',
                'display_name' => 'Home',
                'template' => 'home',
                'article_slot' => 23,
                'deletable' => FALSE,
                'reorderable' => FALSE,
                'article_ids' => ['<article-uuid-1>', '<article-uuid-2>'],
                'subsections' => [
                  [
                    'key' => 'main',
                    'display_name' => 'Main',
                    'article_slot' => 3,
                    'article_ids' => ['<article-uuid-1>', '<article-uuid-2>'],
                  ],
                  ['key' => 'secondary', 'display_name' => 'Secondary', 'article_slot' => 3, 'article_ids' => []],
                  ['key' => 'black_box', 'display_name' => 'Black Box', 'article_slot' => 1, 'article_ids' => []],
                  [
                    'key' => 'blue_articles',
                    'display_name' => 'Blue Articles',
                    'article_slot' => 3,
                    'article_ids' => [],
                  ],
                  ['key' => 'top_stories', 'display_name' => 'Top Stories', 'article_slot' => 13, 'article_ids' => []],
                ],
              ],
              [
                'id' => '9694680d-…',
                'uuid' => '9694680d-…',
                'type' => 'section',
                'internal_name' => 'politiki-home',
                'display_name' => 'Πολιτική',
                'template' => '9_slots',
                'article_slot' => 9,
                'deletable' => TRUE,
                'reorderable' => TRUE,
                'article_ids' => ['<article-uuid-3>'],
              ],
              [
                'id' => '1373163c-…',
                'uuid' => '1373163c-…',
                'type' => 'composite',
                'internal_name' => 'markets-home',
                'display_name' => 'Liberal Markets',
                'template' => 'liberal_markets',
                'article_slot' => 11,
                'deletable' => FALSE,
                'reorderable' => TRUE,
                'article_ids' => ['<article-uuid-4>'],
                'subsections' => [
                  [
                    'key' => 'home_lm',
                    'display_name' => 'Home LM',
                    'article_slot' => 5,
                    'article_ids' => ['<article-uuid-4>'],
                  ],
                  [
                    'key' => 'top_stories',
                    'display_name' => 'Top Stories',
                    'article_slot' => 6,
                    'article_ids' => [],
                  ],
                ],
              ],
            ],
          ]]]],
        ],
        'post' => [
          'tags' => ['Home layout'],
          'summary' => 'Home layout — σώσε τη διάταξη',
          'description' => "Ένα call για τα πάντα — δεν υπάρχουν ξεχωριστά update/delete endpoints. Τι κάνεις εξαρτάται από **τι βάζεις μέσα στο `sections`**.\n\n"
            . "### ⚠️ Δύο διαφορετικές σημασιολογίες στο ίδιο call\n\n"
            . "| Τι στέλνεις | Συμπεριφορά |\n"
            . "|---|---|\n"
            . "| Entries με `uuid` (κανονικά sections) | **FULL REPLACE.** Ο πίνακας ορίζει ποια υπάρχουν και με ποια σειρά. **Ό,τι παραλείψεις διαγράφεται.** |\n"
            . "| Entry με `key: \"home\"` (τα sub-sections) | **MERGE.** Sub-section που παραλείπεις **δεν αγγίζεται**. Μόνο ρητό `\"article_ids\": []` το καθαρίζει. |\n\n"
            . "Είναι σκόπιμο: τα κανονικά sections πρέπει να είναι full-replace για να μπορεί να εκφραστεί διαγραφή και αναδιάταξη· τα sub-sections δεν διαγράφονται ποτέ, οπότε το merge τα προστατεύει από λανθασμένο call.\n\n"
            . "### Τι ΔΕΝ μπορείς να κάνεις, ό,τι κι αν στείλεις\n\n"
            . "- **Δεν διαγράφεις το Home.** Αν το παραλείψεις τελείως, το επόμενο GET το επιστρέφει με τα άρθρα του ανέπαφα.\n"
            . "- **Δεν το μετακινείς.** Είναι πάντα πρώτο· η θέση δεν διαβάζεται από το payload.\n"
            . "- **Δεν διαγράφεις sub-section.** Άγνωστο `key` αγνοείται σιωπηλά.\n"
            . "- **Δεν κόβονται άρθρα σε υπέρβαση slots.** 5 άρθρα σε sub-section με 3 slots αποθηκεύονται και τα 5.\n\n"
            . "### Ταυτοποίηση στο payload\n\n"
            . "Στείλε πίσω το **`id`** όπως το έλαβες από το `GET` — δουλεύει και για το Home και για τα entity sections. Για συμβατότητα γίνονται δεκτά και τα `key` (Home) και `uuid` (entity sections). Άγνωστο ή κενό αναγνωριστικό → **422**.\n\n"
            . "Σε **composite** placement μπορείς να στείλεις είτε `subsections` είτε — όπως και πριν — επίπεδο `article_ids`. Η επίπεδη λίστα μοιράζεται στις ομάδες με τη σειρά και τα slots τους (τα πρώτα 5 στο `home_lm`, τα επόμενα 6 στο `top_stories`), ακριβώς όπως την ερμηνεύει σήμερα ο renderer. Αν στείλεις και τα δύο, υπερισχύουν τα `subsections`.\n\n"
            . "### Από πού παίρνεις τα uuid\n\n"
            . "Παντού μπαίνει το **uuid**, όχι το αριθμητικό id.\n\n"
            . "| Τι | Από πού | Ποιο πεδίο |\n"
            . "|---|---|---|\n"
            . "| `article_ids[]` | `GET /unicornapi/node/article_liberal` ή `/customapi/latest-articles` | `data[].id` (το `drupal_internal__nid` **δεν** χρησιμοποιείται) |\n"
            . "| `uuid` του section | `GET /unicornapi/section/section` | `data[].id` |\n\n"
            . "⚠️ Τα uuid είναι **σταθερά μέσα σε ένα περιβάλλον** αλλά **διαφορετικά ανά περιβάλλον**. Μην τα κάνεις hardcode — διάβασέ τα πάντα από το API του περιβάλλοντος που τρέχεις.\n\n"
            . "### Έγκυρα `key` για τα sub-sections\n\n"
            . "**Home** (`key: \"home\"`): `main` · `secondary` · `black_box` · `blue_articles` · `top_stories`\n\n"
            . "**Liberal Markets** (placement με `uuid`, template `liberal_markets`): `home_lm` · `top_stories`\n\n"
            . "Οτιδήποτε άλλο αγνοείται σιωπηλά.\n\n"
            . "### Συνηθισμένες ενέργειες\n\n"
            . "**Άρθρα σε sub-section** — στέλνεις μόνο αυτό που άλλαξε:\n"
            . "```json\n{\"variant\":\"liberal\",\"sections\":[{\"key\":\"home\",\"subsections\":[{\"key\":\"main\",\"article_ids\":[\"uuid-1\",\"uuid-2\"]}]}]}\n```\n\n"
            . "**Καθάρισμα sub-section** — ρητό άδειο array:\n"
            . "```json\n{\"key\":\"home\",\"subsections\":[{\"key\":\"black_box\",\"article_ids\":[]}]}\n```\n\n"
            . "**Άρθρα στο Liberal Markets** — placement με `uuid` **και** `subsections`, ίδιο merge με το Home:\n"
            . "```json\n{\"uuid\":\"1373163c-…\",\"subsections\":[{\"key\":\"home_lm\",\"article_ids\":[\"uuid-1\"]}]}\n```\n\n"
            . "**Διαγραφή κανονικού section** — το παραλείπεις από τον πίνακα. Το Liberal Markets **δεν** διαγράφεται έτσι: αν λείπει από το payload επιστρέφει με ό,τι είχε.\n\n"
            . "**Αναδιάταξη κανονικών sections** — στέλνεις τον πίνακα με άλλη σειρά.\n\n"
            . "### 🔴 Προσοχή: drag & drop άρθρου ΜΕΤΑΞΥ sub-sections\n\n"
            . "Επειδή η παράλειψη σημαίνει «μην αγγίζεις», όταν μετακινείς άρθρο από ένα sub-section σε άλλο πρέπει να στείλεις **ΚΑΙ ΤΑ ΔΥΟ** — και αυτό που το χάνει, με τον νέο του πίνακα.\n\n"
            . "```json\n// ΣΩΣΤΟ — το E μετακινείται\n{\"key\":\"home\",\"subsections\":[\n  {\"key\":\"main\",\"article_ids\":[\"A\",\"B\",\"C\",\"D\"]},\n  {\"key\":\"top_stories\",\"article_ids\":[\"E\"]}\n]}\n\n// ΛΑΘΟΣ — μόνο ο παραλήπτης· το E μένει ΚΑΙ στα δύο\n{\"key\":\"home\",\"subsections\":[{\"key\":\"top_stories\",\"article_ids\":[\"E\"]}]}\n```\n\n"
            . "Δεν γίνεται αυτόματο dedupe: το ίδιο άρθρο μπορεί νόμιμα να εμφανίζεται σε δύο σημεία.\n\n"
            . "### Σειρά άρθρων\n\n"
            . "Η σειρά του `article_ids` **είναι** η σειρά εμφάνισης. Αποθηκεύεται αυτούσια — για drag & drop μέσα σε sub-section στέλνεις απλώς τον αναδιατεταγμένο πίνακα.\n\n"
            . "**Access:** το `markets` variant απαιτεί `access liberal markets dashboard` (Admin/Editor).",
          'consumes' => ['application/json'],
          'parameters' => [['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => [
            'type' => 'object',
            'example' => [
              'variant' => 'liberal',
              'sections' => [
                [
                  'key' => 'home',
                  'subsections' => [
                    ['key' => 'main', 'article_ids' => ['<article-uuid-1>', '<article-uuid-2>', '<article-uuid-3>']],
                    ['key' => 'secondary', 'article_ids' => ['<article-uuid-4>']],
                    ['key' => 'black_box', 'article_ids' => ['<article-uuid-5>']],
                    ['key' => 'blue_articles', 'article_ids' => []],
                    ['key' => 'top_stories', 'article_ids' => ['<article-uuid-6>']],
                  ],
                ],
                ['uuid' => '<section-uuid-1>', 'article_ids' => ['<article-uuid-7>']],
                [
                  'uuid' => '<liberal-markets-section-uuid>',
                  'subsections' => [
                    ['key' => 'home_lm', 'article_ids' => ['<article-uuid-8>']],
                    ['key' => 'top_stories', 'article_ids' => []],
                  ],
                ],
                ['uuid' => '<section-uuid-2>', 'article_ids' => []],
              ],
            ],
          ]]],
          'responses' => [
            '200' => [
              'description' => 'Saved. Το `count` μετράει και το Home.',
              'examples' => ['application/json' => ['saved' => TRUE, 'variant' => 'liberal', 'count' => 3]],
            ],
            '422' => [
              'description' => 'Κάποιο placement δείχνει σε ανύπαρκτο Section entity.',
              'examples' => ['application/json' => [
                'error' => 'Unknown section uuid(s) — κάθε section πρέπει να δείχνει σε υπαρκτό Section entity.',
                'invalid' => ['aaaa1111-…'],
              ]],
            ],
          ],
        ],
      ],

      // ─────────────── CONTENT FEEDS ───────────────
      "/$api/latest-articles" => [
        'get' => [
          'tags' => ['Content'],
          'summary' => 'Τελευταία άρθρα (για γέμισμα slots)',
          'description' => 'Το `data[].id` είναι το article uuid που βάζεις στο `article_ids` ενός section.',
          'parameters' => [['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 10, 'description' => '1–50.']],
          'responses' => ['200' => ['description' => 'JSON:API λίστα άρθρων.']],
        ],
      ],
      "/$api/article-feed" => [
        'get' => [
          'tags' => ['Content'],
          'summary' => 'Άρθρα με φίλτρα (κατηγορία/αρθρογράφος/tag)',
          'parameters' => [
            ['name' => 'filterBy', 'in' => 'query', 'type' => 'string', 'enum' => ['category', 'author', 'tag'], 'description' => 'Τι φιλτράρεις.'],
            $term_param,
            ['name' => 'sort', 'in' => 'query', 'type' => 'string', 'enum' => ['created', 'changed'], 'default' => 'changed'],
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 20, 'description' => '1–50.'],
          ],
          'responses' => ['200' => ['description' => 'JSON:API λίστα (άδειο data αν άκυρο termUuid).']],
        ],
      ],
      "/$api/popular-articles" => [
        'get' => [
          'tags' => ['Content'],
          'summary' => 'Δημοφιλή άρθρα της ημέρας',
          'description' => 'Επιστρέφει έως 6 δημοσιευμένα άρθρα, ταξινομημένα κατά φθίνουσα ημερήσια καταμέτρηση αναγνώσεων. Η θέση στο `data` είναι η κατάταξη.',
          'responses' => ['200' => ['description' => 'JSON:API λίστα άρθρων.']],
        ],
      ],
      "/$api/news-feed/{type}" => [
        'get' => [
          'tags' => ['Content'],
          'summary' => 'News feed (24ωρο ή paginated)',
          'parameters' => [
            ['name' => 'type', 'in' => 'path', 'type' => 'string', 'required' => TRUE, 'enum' => ['block', 'page'], 'description' => '`block` = τελευταίο 24ωρο· `page` = όλα, paginated.'],
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer'],
            ['name' => 'page[offset]', 'in' => 'query', 'type' => 'integer'],
          ],
          'responses' => ['200' => ['description' => 'JSON:API λίστα.']],
        ],
      ],
      "/$api/node/article_liberal" => [
        'get' => [
          'tags' => ['Content'],
          'summary' => 'Άρθρα (JSON:API) — από εδώ παίρνεις τα article uuids',
          'description' => 'Πλήρης λίστα άρθρων. Το **`data[].id` (uuid)** είναι αυτό που βάζεις στα `article_ids` όταν τοποθετείς section στην αρχική (POST /customapi/home-layout).',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 20],
            ['name' => 'sort', 'in' => 'query', 'type' => 'string', 'enum' => ['-created', 'created', '-changed', 'changed'], 'default' => '-created'],
            ['name' => 'filter[title][operator]', 'in' => 'query', 'type' => 'string', 'enum' => ['CONTAINS', 'STARTS_WITH', '=']],
            ['name' => 'filter[title][value]', 'in' => 'query', 'type' => 'string', 'description' => 'Αναζήτηση τίτλου.'],
          ],
          'responses' => ['200' => ['description' => 'JSON:API λίστα άρθρων.']],
        ],
      ],

      // ─────────────── AUTH ───────────────
      '/oauth/authorize' => [
        'get' => [
          'tags' => ['Auth'],
          'summary' => 'Login — ξεκίνα authorization_code',
          'parameters' => [
            ['name' => 'response_type', 'in' => 'query', 'type' => 'string', 'required' => TRUE, 'default' => 'code'],
            ['name' => 'client_id', 'in' => 'query', 'type' => 'string', 'required' => TRUE],
            ['name' => 'redirect_uri', 'in' => 'query', 'type' => 'string', 'required' => TRUE],
            ['name' => 'scope', 'in' => 'query', 'type' => 'string', 'default' => 'liberal'],
            ['name' => 'state', 'in' => 'query', 'type' => 'string'],
          ],
          'responses' => ['302' => ['description' => 'Redirect πίσω στο redirect_uri με `?code=…`.']],
        ],
      ],
      '/oauth/token' => [
        'post' => [
          'tags' => ['Auth'],
          'summary' => 'Token — code → access/refresh token',
          'consumes' => ['application/x-www-form-urlencoded'],
          'parameters' => [
            ['name' => 'grant_type', 'in' => 'formData', 'type' => 'string', 'required' => TRUE, 'enum' => ['authorization_code', 'refresh_token'], 'default' => 'authorization_code'],
            ['name' => 'code', 'in' => 'formData', 'type' => 'string'],
            ['name' => 'redirect_uri', 'in' => 'formData', 'type' => 'string'],
            ['name' => 'client_id', 'in' => 'formData', 'type' => 'string', 'required' => TRUE],
            ['name' => 'client_secret', 'in' => 'formData', 'type' => 'string', 'required' => TRUE],
          ],
          'responses' => ['200' => ['description' => '{ access_token, refresh_token, expires_in, token_type }']],
        ],
      ],
      '/customapi/session-destroy' => [
        'post' => [
          'tags' => ['Auth'],
          'summary' => 'Logout — σκότωσε το Drupal session',
          'description' => 'Χωρίς body. Θέλει `credentials: include`. Επίσης σβήσε το token client-side.',
          'responses' => ['200' => ['description' => '{ "ok": true }']],
        ],
      ],

      // ─────────────── ACCOUNT (τρέχων χρήστης) ───────────────
      '/customapi/me' => [
        'get' => [
          'tags' => ['Account'],
          'summary' => 'Τρέχων χρήστης — profile + status + roles + capabilities',
          'description' => 'Ταυτότητα, `status` και **capabilities** του logged-in χρήστη. Τα `capabilities` υπολογίζονται από τα Drupal permissions (ΟΧΙ από το role name): map `capability → [actions]`, όπου action = `view`/`create`/`update`/`delete` (CRUD πόροι) ή `access` (tool/section, non-CRUD). Εμφανίζονται μόνο τα granted — απών key ή απόν action = δεν επιτρέπεται. Τα `roles` είναι **labels** (Administrator/Editor/Commercial/Client· τα μη-Liberal roles παραλείπονται). Ο FE κάνει gate το UI από τα capabilities, όχι από τα roles. Απαιτεί token με **`scope=dashboard`** — με `scope=liberal` τα capabilities βγαίνουν σχεδόν άδεια.',
          'responses' => [
            '200' => [
              'description' => 'Ο τρέχων χρήστης + capabilities (παράδειγμα: Editor).',
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'uid' => ['type' => 'integer'],
                  'uuid' => ['type' => 'string'],
                  'name' => ['type' => 'string'],
                  'display_name' => ['type' => 'string'],
                  'mail' => ['type' => 'string'],
                  'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Κατάσταση λογαριασμού (read-only).'],
                  'roles' => [
                    'type' => 'array',
                    'description' => 'Liberal role labels· τα μη-Liberal roles παραλείπονται.',
                    'items' => ['type' => 'string', 'enum' => ['Administrator', 'Editor', 'Commercial', 'Client']],
                  ],
                  'capabilities' => [
                    'type' => 'object',
                    'description' => 'capability → granted actions· μόνο τα granted εμφανίζονται (απών key = δεν επιτρέπεται).',
                    'additionalProperties' => [
                      'type' => 'array',
                      'items' => ['type' => 'string', 'enum' => ['view', 'create', 'update', 'delete', 'access']],
                    ],
                  ],
                ],
              ],
              'examples' => ['application/json' => [
                'uid' => 200,
                'uuid' => '3f2a9c10-7b4e-4a2d-9c88-1e5b7a0d2f11',
                'name' => 'oauth_le_test',
                'display_name' => 'oauth_le_test',
                'mail' => 'editor@liberal.gr',
                'status' => 'active',
                'roles' => ['Editor'],
                'capabilities' => [
                  'user_profile' => ['view', 'update'],
                  'dashboard' => ['access'],
                  'publish_dashboard' => ['access'],
                  'articles' => ['view', 'create', 'update', 'delete'],
                  'sections' => ['view', 'create', 'update', 'delete'],
                  'categories' => ['view', 'create', 'update', 'delete'],
                  'authors' => ['view', 'create', 'update', 'delete'],
                  'settings' => ['access'],
                  'footer' => ['access'],
                  'sources' => ['view', 'create', 'update', 'delete'],
                  'series' => ['view', 'create', 'update', 'delete'],
                  'preview_link' => ['access'],
                  'unpublished_preview' => ['view'],
                ],
              ]],
            ],
            '403' => ['description' => 'Δεν είναι logged in.'],
          ],
        ],
        'patch' => [
          'tags' => ['Account'],
          'summary' => 'Ενημέρωση του δικού μου profile',
          'description' => 'Αλλάζει μόνο το display name του logged-in χρήστη. Δεν δίνει generic Drupal user-edit access και δεν επιτρέπει αλλαγή άλλου λογαριασμού. Απαιτεί capability `user_profile.update`.',
          'consumes' => ['application/json'],
          'parameters' => [
            ['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => ['type' => 'object', 'required' => ['display_name'], 'example' => ['display_name' => 'Μαρία Παπαδοπούλου']]],
          ],
          'responses' => [
            '200' => ['description' => 'Το ενημερωμένο profile, στο ίδιο format με GET /customapi/me.'],
            '403' => ['description' => 'Λείπει το permission manage own liberal profile.'],
            '422' => ['description' => 'Κενό, >60 χαρακτήρες ή ήδη χρησιμοποιημένο display_name.'],
          ],
        ],
      ],
      '/customapi/change-password' => [
        'post' => [
          'tags' => ['Account'],
          'summary' => 'Αλλαγή κωδικού — current → new',
          'description' => 'Αλλάζει τον κωδικό του logged-in χρήστη αφού επαληθεύσει τον τρέχοντα. `new_password` ≥ 8 χαρακτήρες.',
          'consumes' => ['application/json'],
          'parameters' => [
            ['name' => 'body', 'in' => 'body', 'schema' => ['type' => 'object', 'required' => ['current_password', 'new_password'], 'example' => ['current_password' => 'oldpass', 'new_password' => 'newpass123']]],
          ],
          'responses' => [
            '200' => ['description' => '{ "ok": true }'],
            '400' => ['description' => 'current_password / new_password λείπουν'],
            '422' => ['description' => 'new_password < 8 χαρακτήρες'],
            '403' => ['description' => 'current_password λάθος'],
          ],
        ],
      ],

      // ─────────────── FEATURES / ΑΦΙΕΡΩΜΑΤΑ (oblations migration, LIBER-4174) ───────────────
      "/$api/taxonomy_term/oblations" => [
        'get' => [
          'tags' => ['Features'],
          'summary' => 'Αφιερώματα — λίστα + ΠΛΗΡΗΣ οδηγός microsite',
          'description' => 'Κατάλογος ΟΛΩΝ των αφιερωμάτων (oblations) + οδηγός για να χτίσεις τη σελίδα κάθε αφιερώματος `/oblation/{slug}`. Migration παλιών microsites — **καθαρό JSON:API, χωρίς custom endpoints**.

**Δομή:** κάθε αφιέρωμα = ένα term του `oblations`. Τα άρθρα συνδέονται με `field_oblation_category` και χωρίζονται σε 2 ζώνες μέσω `field_article_region`:
- **Ob Zone A** (tid `88098`) → Big-2 template (Main, 10 slots) πάνω
- **Ob Zone B** (tid `88099`) → List κάτω

**Πεδία αφιερώματος (term):** `name`=Τίτλος · `field_ob_internal_name`=internal name (dashboard, unique) · `field_ob_page_layout`=inner template (big_2) · `field_ob_supertitle`=Υπέρτιτλος/kicker · `field_ob_banner`=εικόνα · `field_ob_external_url`=εξωτερικό link · `field_ob_active`=ενεργό · `field_ob_type`=τύπος

**Server-computed πεδία (read-only, μην τα στέλνεις σε POST/PATCH):**
- `article_slots` — πόσα άρθρα χωράει το Main section της εσωτερικής σελίδας. Προκύπτει από το `field_ob_page_layout` → `GET /customapi/section-templates`. **Δεν χρειάζεται join στο dashboard.**
- `page_layout_label` — το display label του `field_ob_page_layout` για εμφάνιση στη λίστα του dashboard.
- `can_be_deleted` — `false` όταν κάποιο Section δείχνει σε αυτό το Feature (US 12.5). Το κουμπί Delete το **δείχνεις** με το capability `special_features.delete` από το `/customapi/account/me` και το **ενεργοποιείς** με αυτό το flag.
- `legacy_url` — το διατηρημένο URL του liberal.gr (`/oblation/{slug}`). **Χρησιμοποίησε αυτό**, όχι το `path.alias`: ο alias είναι καταχωρημένος στο legacy system path `/oblation/{tid}`, οπότε το core `path` field τον επιστρέφει πάντα `null`.
- `created` — ημερομηνία δημιουργίας (US 12.1). Τα taxonomy terms δεν έχουν `created` στη βάση· προκύπτει από το πρώτο revision.

**ΟΙ 4 ΚΛΗΣΕΙΣ (prefix `/unicornapi/`):**

1) Λίστα αφιερωμάτων:
`GET /unicornapi/taxonomy_term/oblations?filter[field_ob_active]=1`

2) Big-2 Main (Zone A) — πάνω:
`GET /unicornapi/node/article_liberal?filter[field_oblation_category.drupal_internal__tid]={TID}&filter[field_article_region.drupal_internal__tid]=88098&sort=field_oblation_zone_a_weight&page[limit]=10`

3) List (Zone B) — κάτω:
`GET /unicornapi/node/article_liberal?filter[field_oblation_category.drupal_internal__tid]={TID}&filter[field_article_region.drupal_internal__tid]=88099&sort=field_oblation_zone_b_weight`

4) Main article (hero):
`GET /unicornapi/node/article_liberal?filter[field_oblation_category.drupal_internal__tid]={TID}&sort=field_oblation_main_article_weig&page[limit]=1`

`{TID}` = το `drupal_internal__tid` του αφιερώματος (από την κλήση 1). Reads flattened (jsonapi_include): τα attributes είναι κατευθείαν στο `data[i]`.

**Feature CRUD (US12.1-12.5)** = JSON:API term CRUD (`POST/PATCH/DELETE /unicornapi/taxonomy_term/oblations`) με fields `field_ob_internal_name`/`field_ob_page_layout`/`field_ob_supertitle`/`field_ob_banner`.

**Feature Dashboard SAVE (US12.6)** = `POST /customapi/feature-layout` (δες παρακάτω) — γράφει bulk ποια άρθρα + σειρά ανά ζώνη.',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'filter[field_ob_active]', 'in' => 'query', 'type' => 'boolean', 'description' => '1 = μόνο ενεργά αφιερώματα.'],
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 50, 'description' => 'Πόσα ανά σελίδα.'],
            ['name' => 'page[offset]', 'in' => 'query', 'type' => 'integer', 'default' => 0, 'description' => 'Offset για paging.'],
            ['name' => 'fields[taxonomy_term--oblations]', 'in' => 'query', 'type' => 'string', 'description' => 'Sparse fieldset. Για τη λίστα του dashboard (US 12.1) φτάνει: `name,field_ob_internal_name,field_ob_page_layout,page_layout_label,article_slots,can_be_deleted,legacy_url,created,changed`'],
          ],
          'responses' => [
            '200' => [
              'description' => 'Λίστα αφιερωμάτων (flattened). Καλύπτει ΟΛΕΣ τις στήλες του US 12.1 με μία κλήση.',
              'examples' => [$jsonapi_ct => [
                'data' => [[
                  'type' => 'taxonomy_term--oblations',
                  'id' => '38314aab-3108-400d-8da3-456319062569',
                  'drupal_internal__tid' => 88382,
                  'name' => 'Δράσεις για ένα καλύτερο αύριο',
                  'field_ob_internal_name' => 'draseis-gia-ena-kalytero-ayrio',
                  'field_ob_page_layout' => 'big_2',
                  'page_layout_label' => '10 articles, 3-4-3',
                  'field_ob_supertitle' => 'Αφιέρωμα ΕΚΕ & ESG',
                  'field_ob_external_url' => NULL,
                  'field_ob_active' => FALSE,
                  'article_slots' => 10,
                  'can_be_deleted' => TRUE,
                  'legacy_url' => '/oblation/draseis-gia-ena-kalytero-ayrio',
                  'created' => '2022-11-02T12:10:00+00:00',
                  'changed' => '2023-01-07T14:22:00+00:00',
                  'path' => ['alias' => NULL],
                ]],
                'meta' => ['count' => 88],
              ]],
            ],
          ],
        ],
        'post' => [
          'tags' => ['Features'],
          'summary' => 'Feature — δημιουργία αφιερώματος (US 12.1)',
          'description' => 'Δημιουργεί νέο Feature (oblations term). Auth: Bearer με ρόλο editor/administrator (permission `create terms in oblations`). Το `field_ob_internal_name` είναι **unique** (422 σε διπλότυπο). Το `field_ob_page_layout` δέχεται τιμές από το `GET /customapi/section-templates` (όσα έχουν `isFeaturePage`) — προς το παρόν `big_2`. Η εικόνα `field_ob_banner` είναι σχέση προς file: ανέβασέ την ξεχωριστά (JSON:API file upload) και βάλε τη στο `relationships`.',
          'consumes' => [$jsonapi_ct],
          'produces' => [$jsonapi_ct],
          'parameters' => [[
            'name' => 'body', 'in' => 'body', 'required' => TRUE,
            'schema' => ['type' => 'object', 'example' => [
              'data' => [
                'type' => 'taxonomy_term--oblations',
                'attributes' => [
                  'name' => 'Αμερικανικές Εκλογές 2024',
                  'field_ob_internal_name' => 'us-elections-2024',
                  'field_ob_page_layout' => 'big_2',
                  'field_ob_supertitle' => 'ΚΑΜΑΛΑ ΧΑΡΙΣ VS ΝΤΟΝΑΛΝΤ ΤΡΑΜΠ',
                  'field_ob_active' => TRUE,
                ],
              ],
            ]],
          ]],
          'responses' => [
            '201' => ['description' => 'Δημιουργήθηκε — το `data.id` είναι το νέο uuid.'],
            '403' => ['description' => 'Χωρίς permission `create terms in oblations`.'],
            '422' => ['description' => 'Validation (π.χ. διπλότυπο `field_ob_internal_name`).'],
          ],
        ],
      ],

      // ─────────────── FEATURE CRUD — UPDATE / DELETE (LIBER-4247) ───────────────
      "/$api/taxonomy_term/oblations/{uuid}" => [
        'patch' => [
          'tags' => ['Features'],
          'summary' => 'Feature — ενημέρωση (US 12.3)',
          'description' => 'Ενημερώνει πεδία ενός Feature. Στείλε **μόνο** τα attributes που αλλάζουν. Το `data.id` πρέπει να ισούται με το `{uuid}` του path. Auth: `edit terms in oblations`.',
          'consumes' => [$jsonapi_ct],
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'uuid', 'in' => 'path', 'required' => TRUE, 'type' => 'string', 'description' => 'Το uuid του oblations term.'],
            ['name' => 'body', 'in' => 'body', 'required' => TRUE, 'schema' => ['type' => 'object', 'example' => [
              'data' => [
                'type' => 'taxonomy_term--oblations',
                'id' => '38314aab-3108-400d-8da3-456319062569',
                'attributes' => [
                  'field_ob_supertitle' => 'Νέος υπέρτιτλος',
                  'field_ob_active' => FALSE,
                ],
              ],
            ]]],
          ],
          'responses' => [
            '200' => ['description' => 'Ενημερώθηκε.'],
            '403' => ['description' => 'Χωρίς permission `edit terms in oblations`.'],
          ],
        ],
        'delete' => [
          'tags' => ['Features'],
          'summary' => 'Feature — διαγραφή (US 12.5)',
          'description' => 'Διαγράφει το Feature (oblations term). Τα άρθρα ΔΕΝ διαγράφονται — μένουν απλώς χωρίς `field_oblation_category`. Auth: `delete terms in oblations`.',
          'parameters' => [
            ['name' => 'uuid', 'in' => 'path', 'required' => TRUE, 'type' => 'string', 'description' => 'Το uuid του oblations term.'],
          ],
          'responses' => [
            '204' => ['description' => 'Διαγράφηκε (No Content).'],
            '403' => ['description' => 'Χωρίς permission `delete terms in oblations`.'],
          ],
        ],
      ],

      // ─────────────── FEATURE DASHBOARD SAVE (US 12.6, LIBER-4239) ───────────────
      '/customapi/feature-layout' => [
        'post' => [
          'tags' => ['Features'],
          'summary' => 'Feature dashboard SAVE — bulk assign άρθρων (US 12.6)',
          'description' => 'Γράφει σε μία κλήση ποια άρθρα ανήκουν σε ένα Feature και σε ποια ζώνη + σειρά. **Full replace**: άρθρα που αφαιρέθηκαν αποσυνδέονται. Γράφει `field_oblation_category` (feature) + `field_article_region` (Ob Zone A/B) + `field_oblation_zone_a/b_weight` (θέση) στα article nodes — ίδιο μοντέλο με το read. `zone_a`/`zone_b` = ordered arrays από article UUIDs.',
          'consumes' => ['application/json'],
          'parameters' => [[
            'name' => 'body', 'in' => 'body', 'required' => TRUE,
            'schema' => [
              'type' => 'object',
              'example' => [
                'feature' => '<oblations term uuid>',
                'zone_a' => ['<article-uuid-1>', '<article-uuid-2>'],
                'zone_b' => ['<article-uuid-3>'],
              ],
            ],
          ]],
          'responses' => [
            '200' => ['description' => 'Saved.', 'examples' => ['application/json' => ['saved' => TRUE, 'feature' => '<uuid>', 'count' => 3]]],
            '422' => ['description' => 'Unknown feature (χρειάζεται oblations term uuid).'],
          ],
        ],
      ],

      // ─────────────── OTHER HELPERS ───────────────
      '/customapi/ai/summary' => [
        'post' => [
          'tags' => ['Helpers'],
          'summary' => 'AI summary άρθρου',
          'consumes' => ['application/json'],
          'parameters' => [['name' => 'body', 'in' => 'body', 'schema' => ['type' => 'object', 'example' => ['text' => '<article body>', 'lang' => 'el', 'max_words' => 60]]]],
          'responses' => ['200' => ['description' => '{ summary, words, source }']],
        ],
      ],
      '/customapi' => [
        'get' => [
          'tags' => ['Helpers'],
          'summary' => 'Catalog — όλα τα endpoints σε JSON',
          'responses' => ['200' => ['description' => 'Λίστα endpoints (path/method/permission).']],
        ],
      ],

      // ─────────────── TAXONOMIES: Categories / Authors / Sources / Series ───────────────
      "/$api/taxonomy_term/category" => [
        'get' => [
          'tags' => ['Taxonomies'],
          'summary' => 'Categories — λίστα (US 7.1)',
          'description' => 'Κατηγορίες για το dropdown του άρθρου και για τον πίνακα Categories.

**`can_be_deleted`** (US 7.4): `false` όταν **κάποιο άρθρο** χρησιμοποιεί την κατηγορία **Ή** κάποια άλλη κατηγορία την έχει ως **Parent**. Και οι δύο συνθήκες κλειδώνουν το Delete.',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'fields[taxonomy_term--category]', 'in' => 'query', 'type' => 'string', 'description' => 'π.χ. `name,parent,path,can_be_deleted,changed`'],
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 50],
          ],
          'responses' => ['200' => [
            'description' => 'Λίστα κατηγοριών (flattened).',
            'examples' => [$jsonapi_ct => ['data' => [
              ['type' => 'taxonomy_term--category', 'id' => '97f845cc-3da4-49a8-b26a-dd3e58a4b5e8', 'name' => 'Πολιτική', 'can_be_deleted' => FALSE],
              ['type' => 'taxonomy_term--category', 'id' => 'b1e2…', 'name' => 'Κατηγορία χωρίς άρθρα', 'can_be_deleted' => TRUE],
            ]]],
          ]],
        ],
      ],
      "/$api/taxonomy_term/arthrografos" => [
        'get' => [
          'tags' => ['Taxonomies'],
          'summary' => 'Authors — λίστα (US 5.1)',
          'description' => 'Αρθρογράφοι.

**`can_be_deleted`**: **πάντα `true`** — το US 5.4 λέει ρητά ότι ο author διαγράφεται *«whether or not he/she is associated with an article»*. Το πεδίο υπάρχει για ομοιομορφία, ώστε ο FE να γράφει την ίδια λογική για κάθε πίνακα.',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'fields[taxonomy_term--arthrografos]', 'in' => 'query', 'type' => 'string', 'description' => 'π.χ. `name,path,can_be_deleted,changed`'],
            ['name' => 'page[limit]', 'in' => 'query', 'type' => 'integer', 'default' => 50],
          ],
          'responses' => ['200' => [
            'description' => 'Λίστα αρθρογράφων (flattened).',
            'examples' => [$jsonapi_ct => ['data' => [
              ['type' => 'taxonomy_term--arthrografos', 'id' => 'a9dc460b…', 'name' => 'Θ. Μαυρίδης', 'can_be_deleted' => TRUE],
            ]]],
          ]],
        ],
      ],
      "/$api/taxonomy_term/piges_arthron" => [
        'get' => [
          'tags' => ['Taxonomies'],
          'summary' => 'Sources — λίστα (US 13.1)',
          'description' => 'Πηγές άρθρων.

**`can_be_deleted`** (US 13.4): `false` όταν η πηγή έχει **έστω ένα** συνδεδεμένο άρθρο — και τα drafts μετράνε, γιατί η ανάλυση λέει «associated articles» χωρίς περιορισμό κατάστασης.',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'fields[taxonomy_term--piges_arthron]', 'in' => 'query', 'type' => 'string', 'description' => 'π.χ. `name,can_be_deleted,changed`'],
          ],
          'responses' => ['200' => [
            'description' => 'Λίστα πηγών (flattened).',
            'examples' => [$jsonapi_ct => ['data' => [
              ['type' => 'taxonomy_term--piges_arthron', 'id' => 'c3f1…', 'name' => 'Associated Press', 'can_be_deleted' => FALSE],
            ]]],
          ]],
        ],
      ],
      "/$api/taxonomy_term/series" => [
        'get' => [
          'tags' => ['Taxonomies'],
          'summary' => 'Series — λίστα (US 13.5)',
          'description' => 'Σειρές για τα Video Articles — dropdown στη φόρμα άρθρου και πίνακας Series.

**`can_be_deleted`** (US 13.8): `false` όταν η σειρά έχει **έστω ένα** συνδεδεμένο video article (και drafts).
**`created`**: ημερομηνία δημιουργίας από το πρώτο revision — τα terms δεν έχουν `created` στη βάση.',
          'produces' => [$jsonapi_ct],
          'parameters' => [
            ['name' => 'fields[taxonomy_term--series]', 'in' => 'query', 'type' => 'string', 'description' => 'π.χ. `name,can_be_deleted,created,changed`'],
          ],
          'responses' => ['200' => [
            'description' => 'Λίστα σειρών (flattened).',
            'examples' => [$jsonapi_ct => ['data' => [
              ['type' => 'taxonomy_term--series', 'id' => 'd41f…', 'name' => 'Liberal Talks', 'can_be_deleted' => FALSE, 'created' => '2026-02-11T09:30:00+00:00'],
            ]]],
          ]],
        ],
      ],

    ];
  }

  /**
   * Distinct τιμές ενός field ενός entity type → enum (max 100). Guarded.
   */
  private function dbEnum($entity_type, $field) {
    try {
      $etm = \Drupal::entityTypeManager();
      if (!$etm->hasDefinition($entity_type)) {
        return [];
      }
      $storage = $etm->getStorage($entity_type);
      $ids = $storage->getQuery()->accessCheck(FALSE)->range(0, 100)->execute();
      $out = [];
      foreach ($storage->loadMultiple($ids) as $entity) {
        $v = $entity->hasField($field) ? $entity->get($field)->value : NULL;
        if ($v !== NULL && $v !== '') {
          $out[$v] = TRUE;
        }
      }
      $out = array_keys($out);
      sort($out);
      return $out;
    }
    catch (\Throwable) {
      return [];
    }
  }

  /**
   * Κατηγορίες → [uuid => name] (max 200, sorted by name). Guarded.
   */
  private function categoryMap() {
    try {
      $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('vid', 'category')->sort('name')->range(0, 200)->execute();
      $map = [];
      foreach ($storage->loadMultiple($ids) as $term) {
        $map[$term->uuid()] = $term->label();
      }
      return $map;
    }
    catch (\Throwable) {
      return [];
    }
  }

}
