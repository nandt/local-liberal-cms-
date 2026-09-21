<?php

use Drupal\node\Entity\Node;

/**
 * Imports the data in the new drupal db.
 */
class Importer
{

    private Mapper $mapper;
    private String $liberalMarketTid;
    private String $analysisTid;
    private String $urgentTid;

    private function log($x)
    {echo json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;}

    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
        $this->liberalMarketTid = $this->getTermIDByName('Liberal market', 'eidi_arthron');
        $this->analysisTid = $this->getTermIDByName('Ανάλυση', 'eidi_arthron');
        $this->urgentTid = $this->getTermIDByName('Επείγον', 'eidi_arthron');
    }

    /**
     * ['category|arthrografos' => [$name => $tid]]
     * @param string $vid (category|arthrografos)
     * @return array<string, array<string, int>>
     */
    function &_taxonomyName_map_tid(string $vid): array{
        if ($this->_taxonomyName_map_tid === null) {
            $this->_taxonomyName_map_tid = [];
        }

        if (!isset($this->_taxonomyName_map_tid[$vid])) {
            $taxonomies = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
            foreach ($taxonomies as $taxonomy) {
                $this->_taxonomyName_map_tid[$vid][$taxonomy->name->value] = $taxonomy->tid->value;
            }

        }
        return $this->_taxonomyName_map_tid[$vid];
    }private $_taxonomyName_map_tid = null;

    /** @return array<string, int> */
    function &_categoryName_map_tid(): array{return $this->_taxonomyName_map_tid('category');}
    /** @return array<string, int> */
    function &_arthrografosName_map_tid(): array{return $this->_taxonomyName_map_tid('arthrografos');}

    /**
     * Find categoryId based on old category name child, parent.
     * @param string|null $category_child
     * @param string|null $category_parent
     * @return int
     * @throws Exception
     */
    public function categoryId(string | null $category_child, string | null $category_parent, string | null $category_substr): int
    {
        if (empty($category_child) && empty($category_parent)) {
            $category = $category_substr;
        }
        else if (empty($category_parent)) {
            $category = $category_child;
        } else {
            $category = $category_parent;
        }

        if (isset($this->_categoryName_map_tid()[$category])) {
            return $this->_categoryName_map_tid()[$category];
        } else {
            throw new Exception("Category $category does not exist.");
        }

    }

    /**
     * Create and save Node based on migrated row.
     * @param $row
     * @param $stocks
     * @throws Exception
     */
    public function node_create($row, $stocks = array()): ?Node
    {
        #if (strlen($row->short_descr_el) > 255) $row->short_descr_el = 'subtitle_short';
        if (empty($row->title_el)) {
            echo "empty title_el: " . $row->title_el . PHP_EOL;
            return null; // throw new Exception('row.title_el is empty');
        }
        #echo json_encode($row->realimg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $timezone = new DateTimeZone("Europe/Athens");
        $timezone_utc = new DateTimeZone("UTC");
        $ts_date = $row->date;
        $ts_cr_dt = $row->cr_dt;
        $ts_up_dt = $row->up_dt;
        $format = 'basic_html';
        $title = $row->title_el;
        $tags = [
            ['target_id' => 139],
            ['target_id' => 140],
            ['target_id' => 145],
        ];

        $eidos_arthrou_values = [];
        /*
        check if Liberal Markets article
         */
        if ($row->market_active != null && $row->market_active > 0) {
            array_push($eidos_arthrou_values, ['target_id' => $this->liberalMarketTid]);
        }
        /*
        check if urgent article
         */
        if ($row->ektakto != null && $row->ektakto > 0) {
            array_push($eidos_arthrou_values, ['target_id' => $this->urgentTid]);
        }

        /*
        check if Analysis article
         */
        if ($row->metoxi_type != null && $row->metoxi_type > 0) {
            array_push($eidos_arthrou_values, ['target_id' => $this->analysisTid]);
        }
        #$tags_old = explode(',', $row->meta_keywords);
        /*
        prepare metohes
         */

        $metohes_values = [];
        if (count($stocks) > 0) {
            foreach ($stocks as $stockName) {
              $metohiTid=$this->getTermIDByName($stockName, "metohes");
              if ($metohiTid!==null) {
                array_push($metohes_values, ['target_id' => $metohiTid]);
              }
            }
        }

        try {
            $category = $this->categoryId($row->cats_title_gr_child, $row->cats_title_gr_mother, $row->cats_title_gr_substr);
        } catch (Exception $e) {
            echo "$e"." Skipping article";
            return null;
        }

        $data = [
            'field_migrated_id' => $row->id,
            'type' => 'article_liberal',
            'title' => $title,
            'status' => $row->isDraft ? 0 : 1,
            'langcode' => 'el', // need to choose between el or en
            'uid' => 1, // need to related with the correct user
            'created' => $ts_cr_dt->getTimestamp(),
            'changed' => $ts_up_dt->getTimestamp(),
            'body' => ['value' => $row->descr_el, 'summary' => $row->short_descr_el, 'format' => $format],
            'field_show_summary' => ['value' => $row->descrPart],
            'field_title' => ['value' => $title, 'format' => $format],
            'field_energos_ypertitlos' => ['value' => $row->titlePart],
            'field_subtitle' => ['value' => $row->uppertitle, 'format' => $format], // ypertitlos
            #'field_impression_image_url' => ['value' => ], // impression_Url = ?
            'field_teleytaia_enimerosi' => ['value' => $ts_date->setTimezone($timezone_utc)->format('Y-m-d H:i:s')],
            'field_eidos_arthrou' => $eidos_arthrou_values,
            #'field_introduction' => ['value' => $row->subtitle, 'format' => $format],
            #'field_introduction' => ['value' => "Dummy: Κάθε μέρα από πέρα ο πρωθυπουργός θα βρίσκεται σε μια αναμέτρηση με την του κόμματος του, τονίζει στο liberal η Αννα Διαμαντόπουλου, εκτιμώντας ότι", 'format' => $format],
            'field_liberal_category' => ['target_id' => $this->categoryId($row->cats_title_gr_child, $row->cats_title_gr_mother, $row->cats_title_gr_substr)],
            'field_liberal_tags' => $this->_prepareArticleTags($row->id),
            'field_weight' => ['value' => $row->order_id],
            #'field_article_region' => ['target_id' => 159], // frontend position
            'field_arthrografos' => ['target_id' => $this->getAuthorTermIDByName($row->author_name)], // needs author relation
            'field_emfanisi_arthrografoy_stin' => $row->showAuthorIndex,
            'field_metohi' => $metohes_values
        ];
        if ($row->caption !== 'empty') { // all custom alias
            #$data['path']['pathauto'] = PathautoState::SKIP;
            #$data['path']['alias'] = preg_replace('/\/[0-9]+$/', '', $row->caption); // convert 'a-b-c/123' to 'a-b-c'
        }
        if (isset($this->mapper->map_image_upload()[$row->realimg])) {
            $data['field_kentriki_fotografia'] = ['target_id' => $this->mapper->map_image_upload()[$row->realimg]];
        }

        $node = \Drupal::entityTypeManager()->getStorage('node')->create($data);
        if (isset($this->mapper->map_content()[$row->id])) {
            $node->nid = $this->mapper->map_content()[$row->id];
        }

        $node->enforceIsNew(false);
        $node->save();
        $this->mapper->map_content()[$row->id] = $node->id();
        return $node;
    }
    public function getAuthorTermIDByName($name, $vid = 'arthrografos')
    {
        if (empty($name) || empty($vid)) {
            return 0;
        }
        $properties = [
            'name' => $name,
            'vid' => $vid,
        ];
        $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
        $term = reset($terms);
        return !empty($term) ? $term->id() : 0;
    }
    public function getTagTermIDByName($name, $vid = 'tags')
    {
        if (empty($name) || empty($vid)) {
            return 0;
        }
        $properties = [
            'name' => $name,
            'vid' => $vid,
        ];
        $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
        $term = reset($terms);
        return !empty($term) ? $term->id() : 0;
    }

    public function _prepareArticleTags(int $article_id)
    {
        $exporter = new Exporter();
        $_existingTags = $exporter->_sql_fetchArticlesTagsNames($article_id);
        $_drupalTagIds = [];
        foreach ($_existingTags as $_existingTag) {
            $drupal_tag = $this->getTagTermIDByName($_existingTag);
            if (!empty($drupal_tag)) {
                $_drupalTagIds[] = [
                    'target_id' => $drupal_tag,
                ];
            }
        }
        return $_drupalTagIds;
    }

    public function getTermIDByName($name, $vid)
    {
        if (empty($name) || empty($vid)) {
            return 0;
        }
        $properties = [
            'name' => $name,
            'vid' => $vid,
        ];
        $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
        return array_key_first($terms);
    }
}
