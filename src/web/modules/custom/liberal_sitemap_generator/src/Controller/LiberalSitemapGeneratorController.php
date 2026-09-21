<?php

namespace Drupal\liberal_sitemap_generator\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\taxonomy\Entity\Term;

class LiberalSitemapGeneratorController extends ControllerBase {

  // How many links the batch will process per session
  private const int BATCHLINKS = 50;
  private const int ARTICLES_BATCHLINKS = 200;
  private const array SUPPORTS_NEW_FILE = ['articles', 'podcasts'];

  /**
  * @var XMLWriter
  */
  private $writer;

  // Break articles into a new file by this number
  private $indexbreak;
  private $schema = 'http://www.sitemaps.org/schemas/sitemap/0.9';
  private $schema_image = 'http://www.google.com/schemas/sitemap-image/1.1';
  private $schema_news = 'http://www.google.com/schemas/sitemap-news/0.9';

  private function setWriter(\XMLWriter $writer) {
    $this->writer = $writer;
  }

  public function getWriter() {
    return $this->writer;
  }

  private function setIndexBreak($variant, $break) {
    // if there is a form value use it, otherwise use 5000
    switch ($variant) {
      case 'articles':
      case 'podcasts':
        $this->indexbreak = $break;
        break;

      default:
        // set the indexbreak each time according to the current month
        $form_val = \Drupal::config('liberal_sitemap_generator_form.settings')->get('batch_max_links');
        $this->indexbreak = (!empty($form_val)) ? $form_val : 5000;
        break;
    }
  }

  private function getIndexBreak() {
    return $this->indexbreak;
  }

  public function getSchema() {
    return $this->schema;
  }

  public function getSchemaNews() {
    return $this->schema_news;
  }

  public function getSchemaImage() {
    return $this->schema_image;
  }

  /**
  * @param string $data
  * Output string from XMLWriter
  * @param string $variant
  * String representing sitemap being generated
  * @param int $file_counter
  * This is used to increment the number on the filename
  */
  public function saveFile($data, $variant, $file_counter = '') {
    $directory = 'public://sitemaps';
    $name = $this->getFilename($variant, $file_counter);

    // check if directory is writeable / create if it does not exist
    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // create the file and make it managable by Drupal
    $fileRepository = \Drupal::service('file.repository');
    $fileRepository->writeData($data, $directory . $name, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
  * @param $date string
  * The date in timestamp format
  */
  public function lastmodDate($date, $is_timestamp = FALSE) {
    // set Europe/Athens
    $timezone = "Europe/Athens";

    // get UTC default timezone dates are stored (its UTC 0)
    $init_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

    if ($is_timestamp) {
      $dateTime = DrupalDateTime::createFromTimestamp($date, $init_timezone);
    }
    else {
      $dateTime = new DrupalDateTime($date, $init_timezone);
    }

    $lastmod = $dateTime->format('c', ['timezone' => $timezone]);

    return $lastmod;
  }

  public function startSitemap($fill = FALSE) {

    $this->setWriter(new \XMLWriter());

    if (!$fill) {
      $this->getWriter()->openMemory();
      $this->getWriter()->startDocument('1.0', 'UTF-8');
      $this->getWriter()->setIndent(TRUE);
      $this->getWriter()->startElement('urlset');
      $this->getWriter()->writeAttribute('xmlns', $this->getSchema());
      // Χωρίς τη δήλωση εδώ, τα <image:image> των άρθρων θα ήταν άκυρο XML — γι'
      // αυτό ολόκληρο το αρχείο των άρθρων δεν έδινε ποτέ εικόνες στο Google Images.
      $this->getWriter()->writeAttribute('xmlns:image', $this->getSchemaImage());
    }
    else {
      $this->getWriter()->openMemory();
      $this->getWriter()->startDocument('1.0', 'UTF-8');
      $this->getWriter()->setIndent(TRUE);
      $this->getWriter()->startElement('urlset');
    }
  }

  // Works for all sitemap variants
  public function endSitemap() {
    $this->getWriter()->endElement();
    $this->getWriter()->endDocument();
  }

  public function startSitemapNews($fill = FALSE) {

    $this->setWriter(new \XMLWriter());

    if (!$fill) {
      $this->getWriter()->openMemory();
      $this->getWriter()->startDocument('1.0', 'UTF-8');
      $this->getWriter()->setIndent(TRUE);
      $this->getWriter()->startElement('urlset');
      $this->getWriter()->writeAttribute('xmlns', $this->getSchema());
      $this->getWriter()->writeAttribute('xmlns:news', $this->getSchemaNews());
      $this->getWriter()->writeAttribute('xmlns:image', $this->getSchemaImage());
    }
    else {
      $this->getWriter()->openMemory();
      $this->getWriter()->startDocument('1.0', 'UTF-8');
      $this->getWriter()->setIndent(TRUE);
      $this->getWriter()->startElement('urlset');
    }
  }

  public function startSitemapIndex($fill = FALSE) {

    $this->setWriter(new \XMLWriter());

    if (!$fill) {
      $this->getWriter()->openMemory();
      $this->getWriter()->startDocument('1.0', 'UTF-8');
      $this->getWriter()->setIndent(TRUE);
      $this->getWriter()->startElement('sitemapindex');
      $this->getWriter()->writeAttribute('xmlns', $this->getSchema());
    }
    else {
      $this->getWriter()->openMemory();
      $this->getWriter()->startDocument('1.0', 'UTF-8');
      $this->getWriter()->setIndent(TRUE);
      $this->getWriter()->startElement('sitemapindex');
    }
  }

  /**
  * @param string $variant
  * @param int $file_counter
  **/
  protected function getFilename($variant, $file_counter = '') {
    switch ($variant) {
      case 'news':
        $filename = '/sitemap_news.xml';
        break;

      case 'articles':
        $fragment = is_array($file_counter) ? ($file_counter['filename_fragment'] ?? '') : '';
        $filename = '/sitemap_ar_' . $fragment . '.xml';
        break;

      case 'index':
        $filename = '/sitemap_index.xml';
        break;

      case 'index_xrimatistirio':
        $filename = '/sitemap_index_xrimatistirio.xml';
        break;

      case 'indices':
        $filename = '/sitemap_indexes.xml';
        break;

      case 'stocks':
        $filename = '/sitemap_stocks.xml';
        break;

      case 'podcasts':
        $fragment = is_array($file_counter) ? ($file_counter['filename_fragment'] ?? '') : '';
        $filename = "/sitemap_video_{$fragment}.xml";
        break;
    }

    return $filename;
  }

  public function addItem($variant, $link) {
    match ($variant) {
        'articles', 'stocks', 'indices', 'podcasts' => $this->addItemArticles($link),
        'news' => $this->addItemNews($link),
        'index', 'index_xrimatistirio' => $this->addItemIndex($link),
        default => $link,
    };

    return $link;
  }

  // write an entry to the XML sitemap
  public function addItemArticles($item) {
    $this->getWriter()->startElement('url');
    $this->getWriter()->writeElement('loc', $item['url']);
    $this->getWriter()->writeElement('lastmod', $item['lastmod']);

    // Μία κεντρική φωτογραφία ανά άρθρο, όπως και στο news sitemap.
    if (!empty($item['image']['path'])) {
      $this->getWriter()->startElement('image:image');
      $this->getWriter()->writeElement('image:loc', $item['image']['path']);

      if (!empty($item['image']['alt'])) {
        $this->getWriter()->writeElement('image:alt', $item['image']['alt']);
      }

      if (!empty($item['image']['title'])) {
        $this->getWriter()->writeElement('image:title', $item['image']['title']);
      }

      // end image
      $this->getWriter()->endElement();
    }

    // end url
    $this->getWriter()->endElement();
  }

  /**
   * Απόλυτο URL εικόνας από το uri της.
   *
   * Το news φορτώνει File entity και το articles παίρνει το uri από join, αλλά
   * και τα δύο καταλήγουν εδώ ώστε το URL να χτίζεται με έναν τρόπο — και με το
   * S3 CNAME να ισχύει και στα δύο sitemaps.
   */
  protected function absoluteImageUrl(string $uri): string {
    return \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
  }

  protected function getLastModeDate($entity, $variant = NULL) {
    switch ($variant) {
      case 'articles':
      case 'podcasts':
        /*
         * Article sitemap makes a special case since we dont load the whole node.
         *
         * Το lastmod πρέπει να διαβάζει το ίδιο πεδίο με το dateModified του
         * JSON-LD, αλλιώς η Google βλέπει δύο διαφορετικές «τελευταίες αλλαγές»
         * για το ίδιο URL. Το changed δεν κάνει: 78% των άρθρων το έχουν στις
         * 2-3/1/2024 από το migration.
         *
         * Το πεδίο είναι ISO string, το created unix timestamp — εξ ου και το
         * δεύτερο όρισμα αλλάζει ανά περίπτωση.
         */
        if (!empty($entity->last_updated)) {
          $lastmod_date = $this->lastmodDate($entity->last_updated);
        }
        else {
          $lastmod_date = $this->lastmodDate($entity->created, TRUE);
        }
        break;

      default:
        if ($entity instanceof Term) {
          $lastmod_date = $entity->getChangedTime();
        }
        else {
          $lastmod_date = $entity->getCreatedTime();
        }

        $lastmod_date = $this->lastmodDate($lastmod_date, TRUE);
    }

    return $lastmod_date;
  }

  public function addItemNews($item) {

    $this->getWriter()->startElement('url');
    $this->getWriter()->writeElement('loc', $item['url']);

    $this->getWriter()->startElement('news:news');
    $this->getWriter()->startElement('news:publication');

    $this->getWriter()->writeElement('news:language', $item['langcode']);
    $this->getWriter()->writeElement('news:name', $item['title']);

    // end news:publication
    $this->getWriter()->endElement();

    $this->getWriter()->writeElement('news:publication_date', $item['publication']);
    $this->getWriter()->writeElement('news:title', $item['title']);

    if (!empty($item['keywords'])) {
      $this->getWriter()->writeElement('news:keywords', $item['keywords']);
    }

    // end news:news
    $this->getWriter()->endElement();

    if (!empty($item['image']['path'])) {
      $this->getWriter()->startElement('image:image');
      $this->getWriter()->writeElement('image:loc', $item['image']['path']);

      if (!empty($item['images']['alt'])) {
        $this->getWriter()->writeElement('image:alt', $item['image']['alt']);
      }

      if (!empty($item['images']['title'])) {
        $this->getWriter()->writeElement('image:title', $item['image']['title']);
      }

      // end image
      $this->getWriter()->endElement();
    }

    // end url
    $this->getWriter()->endElement();
  }

  /**
  * @param string $variant
  * String value of sitemap variant
  * @return array $links
  **/
  public function returnVariantLinks($variant, $chunk, $limit, $options, $dates_sitemap, $count) {
    $links = [];

    $links = match ($variant) {
        'stocks' => \Drupal::service('liberal_sitemap_generator.stocks_sitemap_links')->getStocksSitemapLinks(),
        'podcasts' => \Drupal::service('liberal_sitemap_generator.podcasts_sitemap_links')->getPodcastsSitemapLinks($dates_sitemap, $options, $count),
        'indices' => \Drupal::service('liberal_sitemap_generator.indices_sitemap_links')->getIndicesSitemapLinks(),
        'news' => \Drupal::service('liberal_sitemap_generator.news_sitemap_links')->getNewsSitemapLinksQuery(),
        'articles' => \Drupal::service('liberal_sitemap_generator.articles_sitemap_links')->getArticlesSitemapLinks($dates_sitemap, $options, $limit, $count),
        'index' => \Drupal::service('liberal_sitemap_generator.index_sitemap_links')->getIndexSitemapLinks($chunk, $limit),
        'index_xrimatistirio' => \Drupal::service('liberal_sitemap_generator.index_xrimatistirio_sitemap_links')->getIndexXrimatistirioSitemapLinks(),
        default => $links,
    };

    return $links;
  }

  /**
  * @param array $links
  * Leave only the nid column of the results for use with the select query that gets the batch
  * @return array
  **/
  protected static function flattenLinksArray($links): array {
    $flattened_links = array_column($links, 'nid');

    return $flattened_links;
  }

  protected function batchOperationGetEntities($variant, $links) {
    $entities = [];

    if (!empty($links)) {
      $entities = match ($variant) {
          'index', 'index_xrimatistirio' => \Drupal::entityTypeManager()->getStorage('file')->loadMultiple($links),
          'stocks', 'indices' => \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($links),
          'articles', 'podcasts' => $links,
          default => \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($links),
      };
    }

    return $entities;
  }

  /**
  * @param array $item
  * An array with certain key values prepped for the XMLWriter
  */
  public function addItemIndex($item) {
    $this->getWriter()->startElement('sitemap');
    $this->getWriter()->writeElement('loc', $item['url']);

    // end sitemap
    $this->getWriter()->endElement();
  }

  // ----- Batch Operations Functions -------------
  /**
   * @return mixed[]
   */
  protected function batchOperationGetArticleTags($node): array {
    $tags = [];
    $tag_ids = array_column($node->get('field_liberal_tags')->getValue(), 'target_id');

    if (!empty($tag_ids)) {
      $loaded_tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tag_ids);

      foreach ($loaded_tags as $loaded_tag) {
        $tags[] = $loaded_tag->getName();
      }
    }

    return $tags;
  }

  /**
   * @return mixed[]
   */
  protected function batchOperationPrepareLink($entity, $variant): array {
    $link = [];
    global $base_url;

    switch ($variant) {
      case 'news':
        // assign for clarity
        $node = $entity;
        $lastmod_date = $this->getLastModeDate($node);
        // get article tags
        $tags = $this->batchOperationGetArticleTags($node);

        $link = [
          'nid' => $node->id(),
          'url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE])->toString(),
          'publication' => $lastmod_date,
          'langcode' => $node->language()->getId(),
          'title' => $node->getTitle(),
          'keywords' => implode(',', $tags),
        ];

        if (!empty($node->field_kentriki_fotografia->getValue()[0])) {
          $image_values = $node->field_kentriki_fotografia->getValue()[0];

          $file = File::load($image_values['target_id']);

          // somehow a target_id might exist but not file...
          if (!empty($file)) {
            $image_uri = (string) $file->getFileUri();
            $image_url = $this->absoluteImageUrl($image_uri);

            $link['image'] = [
              'path' => $image_url,
              'title' => $image_values['title'],
              'alt' => $image_values['alt'],
            ];
          }
        }
        break;

      case 'articles':
      case 'podcasts':
        // assign for clarity
        $node = $entity;
        $lastmod_date = $this->getLastModeDate($node, $variant);

        $link = [
          'nid' => $node->nid,
          'url' => Url::fromRoute('entity.node.canonical', ['node' => $node->nid], ['absolute' => TRUE])->toString(),
          'lastmod' => $lastmod_date,
        ];

        // Το uri έρχεται από το join, οπότε δεν χρειάζεται File::load ανά άρθρο.
        if (!empty($node->image_uri)) {
          $link['image'] = [
            'path' => $this->absoluteImageUrl($node->image_uri),
            'title' => $node->image_title ?? '',
            'alt' => $node->image_alt ?? '',
          ];
        }
        break;

      case 'stocks':
      case 'indices':
        $taxonomy = $entity;
        $lastmod_date = $this->getLastModeDate($taxonomy);

        if ($variant == 'stocks') {
          $symbol = $taxonomy->get('field_stc_symbol_en')->value;
          $xr_url = '/live-ase-market/stock/' . $symbol;
        }
        else {
          $symbol = $taxonomy->get('field_ase_idx_symbol_en')->value;
          $xr_url = '/live-ase-market/index/' . $symbol;
        }

        $link = [
          'tid' => $taxonomy->id(),
          'url' => $base_url . $xr_url,
          'lastmod' => $lastmod_date,
        ];
        break;

      case 'index':
      case 'index_xrimatistirio':
        // assign for clarity
        $file = $entity;
        $link = [
          'fid' => $file->id(),
          'url' => $base_url . '/' . $file->getFilename(),
        ];
        break;
    }

    return $link;
  }

  protected function batchOperationNextRun(&$context, $variant) {
    switch ($variant) {
      case 'news':
        $this->startSitemapNews(TRUE);
        break;

      case 'index':
      case 'index_xrimatistirio':
        $this->startSitemapIndex(TRUE);
        break;

      case 'stocks':
      case 'indices':
        $this->startSitemap(TRUE);
        break;
    }
  }

  protected function batchOperationNextRunNewFile(&$context, $variant) {
    switch ($variant) {
      case 'articles':
      case 'podcasts':
        if (isset($context['sandbox']['newfile']) && $context['sandbox']['newfile']) {
          $this->startSitemap();
        }
        else {
          $this->startSitemap(TRUE);
        }
        break;
    }
  }

  protected function batchOperationStart(&$context, $variant, $options) {

    $context['sandbox'] = [];
    $context['results']['xml'] = '';
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_node'] = 0;
    $context['sandbox']['chunks'] = [];
    $context['sandbox']['dates_sitemap'] = [];
    $this->setIndexBreak($variant, 0);

    $max = $this->returnVariantLinks($variant, 0, $this->indexbreak, $options, $context['sandbox']['dates_sitemap'], TRUE);
    // Save node count for the termination.
    $context['sandbox']['max'] = $max['results'] ?? count($max);

    // initialize the document only once in the batch process

    if (in_array($variant, self::SUPPORTS_NEW_FILE)) {
      // increment each time we create a new file
      $context['sandbox']['file_counter'] = 1;

      // init counter for breaking files for index
      $context['sandbox']['indexbreak'] = 0;
    }

    switch ($variant) {
      case 'articles':
      case 'stocks':
      case 'indices':
      case 'podcasts':
        $this->startSitemap();
        break;

      case 'news':
        $this->startSitemapNews();
        break;

      case 'index':
      case 'index_xrimatistirio':
        $this->startSitemapIndex();
        break;
    }
  }

  protected function batchOperationClearBuffer($buffer, $variant) {
    $temp_write = preg_replace('/<\?xml version="1\.0" encoding="UTF-8"\?>[\n\r]/', '', (string) $buffer);

    $temp_write = match ($variant) {
        'index', 'index_xrimatistirio' => preg_replace('/<sitemapindex>[\n\r]/', '', (string) $temp_write),
        default => preg_replace('/<urlset>[\n\r]/', '', (string) $temp_write),
    };

    return $temp_write;
  }

  protected function batchOperationProgressCounter(&$context, $source) {
    // No items to process — mark batch finished so Drupal does not loop forever.
    if (empty($context['sandbox']['max'])) {
      $context['finished'] = 1;
      return;
    }
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];

      // if drush pass all sandbox vars to result then get again. Probably bug
      if ($source == 'drush') {
        $context['results']['sandbox_storage'] = $context['sandbox'];
      }
    }
  }

  protected function batchOperationRetainDrushVars(&$context, $source) {
    // first check if $drush true and try to solve the bug
    if ($source == 'drush' && !empty($context['results']['sandbox_storage'])) {
      // get back data from previous iteration
      $context['sandbox'] = $context['results']['sandbox_storage'];
    }
  }

  protected function batchOperationResetIndex(&$context) {
    $context['results']['xml'] = '';
    $context['sandbox']['newfile'] = TRUE;
    $context['sandbox']['file_counter']++;
    $context['sandbox']['indexbreak'] = 0;
  }

  protected function batchOperationProgress($variant, &$context, $link) {
    if (in_array($variant, self::SUPPORTS_NEW_FILE)) {
      // advance break counter
      $context['sandbox']['indexbreak']++;
    }

    $context['sandbox']['progress']++;

    if (!empty($link['fid'])) {
      $context['sandbox']['current_node'] = $link['fid'];
    }
    elseif (!empty($link['tid'])) {
      $context['sandbox']['current_node'] = $link['tid'];
    }
    else {
      $context['sandbox']['current_node'] = $link['nid'];
    }
  }

  // ------ END Batch Operation Functions

  /**
  * The function of the batch process
  * Which type of sitemap is being generated. Usually comes from set_batch
  *
  * @param array $context
  * Drupal uses this to store data about the batch process. We don't input it
  */
  public function sitemapBatchProcess($variant, $options, $source, &$context) {
    $this->batchOperationRetainDrushVars($context, $source);

    // set once only in operation start
    if (empty($context['sandbox'])) {
      $this->batchOperationStart($context, $variant, $options);
      $first_run = TRUE;
    }
    else {
      if (in_array($variant, self::SUPPORTS_NEW_FILE)) {
        $this->batchOperationNextRunNewFile($context, $variant);
      }
      else {
        $this->batchOperationNextRun($context, $variant);
      }
    }

    // get all the links for the limit of the file
    if (empty($context['sandbox']['chunks'])) {
      $links = $this->returnVariantLinks($variant, $context['sandbox']['current_node'], $this->indexbreak, $options, $context['sandbox']['dates_sitemap'], FALSE);

      if ($variant == 'articles' || $variant == 'podcasts') {
        $context['sandbox']['dates_sitemap'] = $links['dates_sitemap'];
        $context['sandbox']['current_date'] = $links['current_date'];
        $links = $links['results'];
      }

      // get dynamic index break
      if (in_array($variant, self::SUPPORTS_NEW_FILE)) {
        switch ($variant) {
          case 'articles':
          case 'podcasts':
            $context['sandbox']['indexbreak_set'] = count($links);
            $this->setIndexBreak($variant, count($links));
            $context['sandbox']['indexbreak_set'] = $this->getIndexBreak();
            break;
        }
      }

      $context['sandbox']['chunks'] = match ($variant) {
          'articles', 'podcasts' => array_chunk($links, self::ARTICLES_BATCHLINKS, TRUE),
          default => array_chunk($links, self::BATCHLINKS),
      };
    }

    $links = array_shift($context['sandbox']['chunks']);
    $entities = $this->batchOperationGetEntities($variant, $links);

    foreach ($entities as $entity) {

      $entity = (object) $entity;
      $link = $this->batchOperationPrepareLink($entity, $variant);
      $this->addItem($variant, $link);
      $this->batchOperationProgress($variant, $context, $link);

      // if we reach index break while looping, end loop immediately
      if (isset($context['sandbox']['indexbreak']) &&
        $context['sandbox']['indexbreak'] >= $context['sandbox']['indexbreak_set']) {
        //exit loop
        break;
      }
    }

    // if first run dont remove xml header, otherwise remove it
    if (isset($first_run) && $first_run === TRUE) {
      // if XML happens to end in first run, end the document
      if ($context['sandbox']['progress'] >= $context['sandbox']['max']) {
        $this->endSitemap();
      }

      $context['results']['xml'] .= $this->getWriter()->outputMemory();
    }
    else {
      // Now we are in the 2nd run and subsequent runs. First run is covered above

      // end document before writing buffer to file
      if ($context['sandbox']['progress'] >= $context['sandbox']['max'] ||
        (isset($context['sandbox']['indexbreak']) && $context['sandbox']['indexbreak'] >= $context['sandbox']['indexbreak_set'])) {
        $this->endSitemap();
      }

      if (isset($context['sandbox']['newfile']) && $context['sandbox']['newfile']) {
        // do new page then set the flag to false
        $temp_write = $this->getWriter()->outputMemory();
        $context['sandbox']['newfile'] = FALSE;
      }
      else {
        // not a new page, do preg replace normally
        $temp_write = $this->batchOperationClearBuffer($this->getWriter()->outputMemory(), $variant);
      }

      // concatenate and resume
      $context['results']['xml'] .= $temp_write;
      unset($temp_write);
    }

    // write to file if there are no more links
    if ($context['sandbox']['progress'] >= $context['sandbox']['max'] ||
       isset($context['sandbox']['indexbreak']) && $context['sandbox']['indexbreak'] >= $context['sandbox']['indexbreak_set']) {

      // output the string to a file
      if (in_array($variant, self::SUPPORTS_NEW_FILE)) {
        match ($variant) {
            'articles', 'podcasts' => $this->saveFile($context['results']['xml'], $variant, $context['sandbox']['current_date']),
            default => $this->saveFile($context['results']['xml'], $variant, $context['sandbox']['file_counter']),
        };
      }
      else {
        $this->saveFile($context['results']['xml'], $variant);
        $context['results']['xml'] = '';
      }

      // reset index break to start new file
      if (in_array($variant, self::SUPPORTS_NEW_FILE)) {
        $this->batchOperationResetIndex($context);
      }
    }

    $this->batchOperationProgressCounter($context, $source);
  }

  /**
  * @param string $variant
  * String value for the type of sitemap to generate
  * @param boolean $options
  * The drush command options
  * @param string $source
  * Whether this batch is being executed from a form submit or not
  */
  public function setExecuteBatch($variant, $options, $source = 'no-form') {
    // See https://www.php.net/manual/en/function.call-user-func.php

    $operations = [
      [[$this, 'sitemapBatchProcess'], [$variant, $options, $source]],
    ];

    $batch = [
      'title' => t('Generating sitemap @variant', ['@variant' => $variant]),
      'operations' => $operations,
      'init_message'     => t('Preparing...'),
      'progress_message' => t('Processed @current links out of @total'),
      'error_message'    => t('An error occurred during processing'),
      'finished' => [$this, 'sitemapBatchFinished'],
    ];

    batch_set($batch);
    /* Only if batch not executed from form submit handler. Redirect to "user"
     *  after batch is finished.
     *  Return batch if it was executed from a form
     *  Another way to execute via drush
     */

    switch ($source) {
      case 'form':
        return $batch;

      break;
      case 'no-form':
        // url to redirect to in case this process was not ran from a form
        return batch_process('user');

      break;
      case 'drush':
        drush_backend_batch_process();
        break;
    }
  }

  // Function that is running on batch finish
  public function sitemapBatchFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Sitemap generated succesfully.'));
    }
    else {
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
