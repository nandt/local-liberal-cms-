<?php

declare(strict_types=1);

namespace Drupal\unicorn_api_alterations\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\metatag\MetatagManager;
use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Επιστρέφει τα metatags και το JSON-LD μιας διαδρομής στον headless front-end.
 */
final readonly class MetatagRouteController implements ContainerInjectionInterface {

  private const array ENTITY_ROUTE_PARAMETERS = ['node', 'taxonomy_term', 'user'];

  private const string CATEGORY_FIELD = 'field_liberal_category';

  private const string CATEGORY_VOCABULARY = 'category';

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private MetatagManager $metatagManager,
    private RequestStack $requestStack,
    private ModuleHandlerInterface $moduleHandler,
    private ConfigFactoryInterface $configFactory,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('metatag.manager'),
      $container->get('request_stack'),
      $container->get('module_handler'),
      $container->get('config.factory'),
    );
  }

  public function getData(): CacheableJsonResponse {
    $response = new CacheableJsonResponse();
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args:path']);

    $request = $this->requestStack->getCurrentRequest();
    $path = $request === NULL ? '' : (string) $request->query->get('path', '');

    if ($path === '') {
      return $this->error($response, 400, 'The "path" query parameter is required.');
    }

    $protected = $this->metatagManager->protectedDefaults();
    $isProtected = in_array($path, $protected, TRUE);

    if (!$isProtected && !str_starts_with($path, '/')) {
      return $this->error($response, 400, 'Path should start with /.');
    }

    $routeName = NULL;
    $routeParameters = [];

    if ($isProtected) {
      $routeName = $path;
    }
    else {
      try {
        $url = Url::fromUserInput($path);
      }
      catch (\Exception) {
        return $this->error($response, 400, 'The route is not valid.');
      }

      if (!$url->isRouted()) {
        return $this->error($response, 404, 'No route found for this path.');
      }

      $routeName = $url->getRouteName();
      $routeParameters = $url->getRouteParameters();
    }

    $entity = $this->resolveEntity($routeParameters);
    $metatags = $this->collectMetatags($entity, $routeName);

    if ($metatags === []) {
      return $this->error($response, 404, 'No metatags found for this path.');
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags(['config:metatag.metatag_defaults.global']);

    if ($routeName !== NULL) {
      $cacheability->addCacheTags(['config:metatag.metatag_defaults.' . $routeName]);
    }

    if ($entity !== NULL) {
      $cacheability->addCacheableDependency($entity);
    }

    // Το όνομα του site μπαίνει στον WebSite κόμβο του @graph, οπότε μια αλλαγή
    // του πρέπει να ακυρώνει και αυτή την απάντηση.
    $cacheability->addCacheableDependency($this->configFactory->get('system.site'));

    $response->setData([
      'path' => $path,
      'route' => $routeName,
      'entity' => $this->describeEntity($entity),
      'breadcrumb' => $this->buildBreadcrumb($entity, $cacheability),
      'tags' => $this->buildTags($metatags, $entity),
      'jsonld' => $this->buildJsonLd($metatags, $entity),
    ]);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Η αλυσίδα κατηγοριών της σελίδας, από τη ρίζα προς το φύλλο.
   *
   * Ο FE χτίζει από αυτήν και την ορατή μπάρα και το BreadcrumbList JSON-LD. Δεν
   * μπορεί να τη βγάλει μόνος του: το alias του άρθρου κουβαλά μόνο την κατηγορία
   * -φύλλο (/epiheiriseis/…), ενώ 45 από τους 127 όρους έχουν γονέα. Η ιεραρχία
   * ζει μόνο εδώ.
   *
   * Οι διαδρομές γυρίζουν σχετικές, όπως και το ίδιο το `path` του αιτήματος — το
   * host το βάζει ο FE, που το ξέρει καλύτερα από το Drupal σε headless στήσιμο.
   *
   * @return list<array{id: int, name: string, url: string}>
   */
  private function buildBreadcrumb(?ContentEntityInterface $entity, CacheableMetadata $cacheability): array {
    if ($entity === NULL) {
      return [];
    }

    // Σε σελίδα κατηγορίας η αλυσίδα είναι η ίδια της η ιεραρχία· σε άρθρο, αυτή
    // της κατηγορίας του.
    if ($entity instanceof TermInterface && $entity->bundle() === self::CATEGORY_VOCABULARY) {
      $term = $entity;
    }
    elseif ($entity->hasField(self::CATEGORY_FIELD)) {
      $term = $entity->get(self::CATEGORY_FIELD)->entity;
    }
    else {
      return [];
    }

    if (!$term instanceof TermInterface) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($storage instanceof TermStorageInterface);

    // Το loadAllParents γυρίζει φύλλο-πρώτα και συμπεριλαμβάνει τον ίδιο τον όρο.
    $chain = array_reverse($storage->loadAllParents((int) $term->id()));
    $breadcrumb = [];

    foreach ($chain as $level) {
      $cacheability->addCacheableDependency($level);

      $breadcrumb[] = [
        'id' => (int) $level->id(),
        'name' => (string) $level->label(),
        'url' => $level->toUrl()->toString(),
      ];
    }

    return $breadcrumb;
  }

  /**
   * @param array<string, mixed> $routeParameters
   */
  private function resolveEntity(array $routeParameters): ?ContentEntityInterface {
    foreach (self::ENTITY_ROUTE_PARAMETERS as $entityTypeId) {
      if (!isset($routeParameters[$entityTypeId])) {
        continue;
      }

      $candidate = $routeParameters[$entityTypeId];

      if ($candidate instanceof ContentEntityInterface) {
        return $candidate;
      }

      $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($candidate);

      if ($entity instanceof ContentEntityInterface) {
        return $entity;
      }
    }

    return NULL;
  }

  /**
   * @return array<string, mixed>
   */
  private function collectMetatags(?ContentEntityInterface $entity, ?string $routeName): array {
    if ($entity !== NULL) {
      $metatags = $this->metatagManager->tagsFromEntityWithDefaults($entity);
    }
    else {
      $metatags = $this->defaultsForRoute($routeName);
    }

    $context = ['entity' => &$entity];
    $this->moduleHandler->alter('metatags', $metatags, $context);

    return $metatags;
  }

  /**
   * @return array<string, mixed>
   */
  private function defaultsForRoute(?string $routeName): array {
    $storage = $this->entityTypeManager->getStorage('metatag_defaults');
    $metatags = [];

    foreach (array_filter(['global', $routeName]) as $id) {
      $defaults = $storage->load($id);

      if ($defaults !== NULL && $defaults->status()) {
        $metatags = array_merge($metatags, (array) $defaults->get('tags'));
      }
    }

    return $metatags;
  }

  /**
   * @param array<string, mixed> $metatags
   *
   * @return list<array{tag: string, attributes: array<string, mixed>, value?: mixed}>
   */
  private function buildTags(array $metatags, ?ContentEntityInterface $entity): array {
    $elements = $this->metatagManager->generateRawElements($metatags, $entity);
    $tags = [];

    foreach ($elements as $element) {
      if (empty($element['#tag'])) {
        continue;
      }

      $attributes = $element['#attributes'] ?? [];

      if (!empty($attributes['schema_metatag'])) {
        continue;
      }

      $tag = [
        'tag' => $element['#tag'],
        'attributes' => $attributes,
      ];

      if (isset($element['#value'])) {
        $tag['value'] = $element['#value'];
      }

      $tags[] = $tag;
    }

    return $tags;
  }

  /**
   * @param array<string, mixed> $metatags
   *
   * @return array<mixed>|null
   */
  private function buildJsonLd(array $metatags, ?ContentEntityInterface $entity): ?array {
    if (!$this->moduleHandler->moduleExists('schema_metatag')) {
      return NULL;
    }

    $elements = $this->metatagManager->generateElements($metatags, $entity);
    $head = $elements['#attached']['html_head'] ?? $elements;
    $items = SchemaMetatagManager::parseJsonld($head);

    return $items === [] ? NULL : $this->addWebSite($this->hoistOrganization($items));
  }

  /**
   * Προσθέτει τον site-wide WebSite κόμβο στο @graph.
   *
   * Δηλώνει την ταυτότητα του site — όνομα και canonical URL — ώστε η Google να
   * μην τη μαντεύει από το <title>. Είναι σταθερός: ίδιος σε κάθε σελίδα, γι'
   * αυτό και δένεται με τον Organization μέσω @id αντί να τον επαναλαμβάνει.
   *
   * Το potentialAction/SearchAction (sitelinks search box) ΔΕΝ μπαίνει εδώ: το
   * Το potentialAction/SearchAction (sitelinks search box) εκπέμπεται **μόνο** αν
   * έχει οριστεί το SEO_SEARCH_PATH. Το target πρέπει να είναι URL που ανοίγει
   * πραγματικά σε browser — η Google το δοκιμάζει — και ο headless FE δεν έχει
   * ακόμα σελίδα αναζήτησης. Χωρίς τη μεταβλητή ο κόμβος βγαίνει χωρίς αυτό, που
   * είναι έγκυρο· με αυτήν ενεργοποιείται χωρίς αλλαγή κώδικα.
   *
   * @param array<mixed> $items
   *
   * @return array<mixed>
   */
  private function addWebSite(array $items): array {
    if (!isset($items['@graph']) || !is_array($items['@graph'])) {
      return $items;
    }

    $organizationId = NULL;
    $base = '';

    foreach ($items['@graph'] as $node) {
      if (!is_array($node) || ($node['@type'] ?? NULL) !== 'Organization') {
        continue;
      }

      $organizationId = isset($node['@id']) ? (string) $node['@id'] : NULL;
      $base = rtrim((string) ($node['url'] ?? ''), '/');
      break;
    }

    // Χωρίς Organization — π.χ. σε σελίδα που δεν εκθέτει publisher — το host το
    // δίνει το ίδιο το αίτημα, ώστε ο κόμβος να μη λείπει από εκείνες τις σελίδες.
    if ($base === '') {
      $request = $this->requestStack->getCurrentRequest();
      $base = $request === NULL ? '' : rtrim($request->getSchemeAndHttpHost(), '/');
    }

    if ($base === '') {
      return $items;
    }

    $website = [
      '@type' => 'WebSite',
      '@id' => $base . '/#website',
      'url' => $base . '/',
      'name' => (string) $this->configFactory->get('system.site')->get('name'),
    ];

    if ($organizationId !== NULL && $organizationId !== '') {
      $website['publisher'] = ['@id' => $organizationId];
    }

    $searchAction = $this->searchAction($base);

    if ($searchAction !== NULL) {
      $website['potentialAction'] = $searchAction;
    }

    // Αμέσως μετά τον Organization: πρώτα ποιοι είμαστε, μετά τι είναι το site,
    // μετά το περιεχόμενο της σελίδας.
    $position = $organizationId === NULL ? 0 : 1;
    array_splice($items['@graph'], $position, 0, [$website]);

    return $items;
  }

  /**
   * Το SearchAction που ενεργοποιεί το sitelinks search box.
   *
   * Το SEO_SEARCH_PATH κρατά τη διαδρομή αναζήτησης του front-end με το
   * placeholder μέσα, π.χ. `/search?q={search_term_string}`. Το host το βάζουμε
   * εμείς από τον ίδιο τον WebSite κόμβο, ώστε να ακολουθεί το περιβάλλον.
   *
   * Χωρίς τη μεταβλητή γυρίζει NULL και δεν εκπέμπεται τίποτα: καλύτερα κανένα
   * SearchAction παρά ένα που δείχνει σε σελίδα που δεν απαντά — η Google
   * δοκιμάζει το target και απορρίπτει το markup.
   *
   * @return array<string, mixed>|null
   */
  private function searchAction(string $base): ?array {
    $path = trim((string) getenv('SEO_SEARCH_PATH'));

    if ($path === '' || !str_contains($path, '{search_term_string}')) {
      return NULL;
    }

    return [
      '@type' => 'SearchAction',
      'target' => $base . '/' . ltrim($path, '/'),
      'query-input' => 'required name=search_term_string',
    ];
  }

  /**
   * @param array<mixed> $items
   *
   * @return array<mixed>
   */
  private function hoistOrganization(array $items): array {
    if (!isset($items['@graph']) || !is_array($items['@graph'])) {
      return $items;
    }

    $organization = NULL;

    foreach ($items['@graph'] as &$node) {
      if (!is_array($node) || !isset($node['publisher']) || !is_array($node['publisher'])) {
        continue;
      }

      if (($node['publisher']['@type'] ?? NULL) !== 'Organization') {
        continue;
      }

      if ($organization === NULL) {
        $organization = $node['publisher'];
        $url = (string) ($organization['url'] ?? '');
        $organization['@id'] = rtrim($url, '/') . '/#organization';
      }

      $node['publisher'] = ['@id' => $organization['@id']];
    }
    unset($node);

    if ($organization === NULL) {
      return $items;
    }

    array_unshift($items['@graph'], $organization);

    return $items;
  }

  /**
   * @return array{type: string, bundle: string, id: int, uuid: string|null}|null
   */
  private function describeEntity(?ContentEntityInterface $entity): ?array {
    if ($entity === NULL) {
      return NULL;
    }

    return [
      'type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'id' => (int) $entity->id(),
      'uuid' => $entity->uuid(),
    ];
  }

  private function error(CacheableJsonResponse $response, int $status, string $message): CacheableJsonResponse {
    $response->setData(['error' => $message]);
    $response->setStatusCode($status);
    $response->getCacheableMetadata()->addCacheTags(['4xx-response']);

    return $response;
  }

}
