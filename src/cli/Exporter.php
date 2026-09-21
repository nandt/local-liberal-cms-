<?php

/**
 * Gets data from the ms sql server.
 */
class Exporter
{

    private $conn;

    public function __construct()
    {
        if (($this->conn = sqlsrv_connect("85.234.145.7", ['TrustServerCertificate' => 1, 'Database' => 'master', 'UID' => 'liberalusr', 'PWD' => 'lib3r@usr423978!', 'CharacterSet' => 'UTF-8', 'MultipleActiveResultSets' => '0', 'Encrypt' => true])) === false) {
            throw new Exception("Cannot connect to ms sql server");
        }

    }

    /**
     * Get the selection count of content_selection_final() without caching.
     * @return mixed
     * @throws Exception
     */
    public function content_count()
    {
        #if (($stmt = sqlsrv_query($this->conn, "SELECT COUNT ([content].[id]) FROM [Liberal].[dbo].[content] [content]")) === false)
        if (($stmt = sqlsrv_query($this->conn, "SELECT COUNT (*) FROM ({$this->content_selection_final()}) AS c")) === false) {
            throw new Exception("Cannot count: " . json_encode(sqlsrv_errors()));
        }

        return sqlsrv_fetch_array($stmt)[0];
    }

    /**
     * Basic selection with limit as parameter.
     * @param int|null $limit
     * @return string
     */
    private function _sql_selectContent(int | null $limit = null): string
    {
        $limit_select = $limit !== null ? "TOP ({$limit})" : "";
        return "
      SELECT
        {$limit_select}
        [cats].[cats_id],
        [cats].[cats_title_gr] [cats_title_gr_child],
        [cats_mother].[cats_title_gr] [cats_title_gr_mother],
        [cats_substr].[cats_title_gr] [cats_title_gr_substr],
        [content].[id],
        [content].[isDraft],
        [content].[title_el],
        [content].[uppertitle],
        [content].[descr_el],
        [content].[short_descr_el],
        [content].[titlePart],
        [content].[descrPart],
        [content].[date],
        [content].[cr_dt],
        [content].[up_dt],
        [content].[order_id],
        [content].[realimg],
        [content].[cropimg],
        [content].[showAuthorIndex],
        [content].[caption],
        [authors].[author_name]
      FROM [Liberal].[dbo].[content] [content]
      LEFT JOIN [Liberal].[dbo].[cats] [cats] ON [content].[catid] = [cats].[cats_Z_id]
      LEFT JOIN [Liberal].[dbo].[cats] [cats_mother] ON [cats].[cats_mother_id] = [cats_mother].[cats_id]
      LEFT JOIN [Liberal].[dbo].[cats] [cats_substr] ON LEFT([content].[catid], 3) = [cats_substr].[cats_Z_id]
      LEFT JOIN [Liberal].[dbo].[authors] [authors] ON [authors].[author_id] = [content].[userid]
    ";
    }

    /**
     * Select by category name.
     * @param string $categoryName
     * @param int|null $limit
     * @return string
     */
    private function _sql_selectBy_categoryName_limit(string $categoryName, int $limit = null): string
    {
        return $this->_sql_selectContent($limit) . "
      WHERE [cats].[cats_title_gr] IN ('{$categoryName}') OR [cats_mother].[cats_title_gr] IN ('{$categoryName}')
    ";
    }

    /**
     * Select 100 articles for each of 10 categories.
     * @return string "SELECT {test_selection_by_category} UNION {repeat_for_all_categories}"
     */
    private function _sql_selectUnion(): string
    {
        $categoryNames = ['ΠΟΛΙΤΙΚΗ', 'ΟΙΚΟΝΟΜΙΑ', 'ΔΙΕΘΝΗ ΘΕΜΑΤΑ', 'ΑΜΥΝΑ & ΔΙΠΛΩΜΑΤΙΑ', 'Πολιτισμός', 'ΕΠΙΚΑΙΡΟΤΗΤΑ', 'ΑΓΟΡΕΣ', 'Θ. Μαυρίδης', 'Κ. Χαροκόπος', 'Δ. Καμπουράκης'];
        $sql_select = "";
        foreach ($categoryNames as $i => $categoryName) {
            $sql_select .= $this->_sql_selectBy_categoryName_limit($categoryName, 100);
            if ($i < count($categoryNames) - 1) {
                $sql_select .= " UNION ";
            }

        }
        return $sql_select;
    }

    /**
     * Selection to use in the final production migration script.
     * @return string
     */
    private function _sql_selectProduction(): string
    {
        return $this->_sql_selectContent(null);
        #return $this->_sql_selectContent(null) . " WHERE [content.id] <= 1000";
        #return $this->_sql_selectContent(null) . " WHERE [content.id] > 1000";
    }

    /**
     * Returns the final sql command that is used publicly by content_fetch().
     * @return string
     */
    private function content_selection_final(int $start_from = null)
    {
        if ($start_from === null) {
          $start_from = 0;
        }
        echo "$start_from\n";
        return $this->_sql_selectContent(10000)." WHERE id > $start_from ORDER BY id ASC";
        // return $this->_sql_selectContent(200)." ORDER by up_dt desc";

        // return $this->_sql_selectUnion();
        #return $this->_sql_selectProduction();
        #return $this->_sql_selectContent() . " WHERE [content].[id] IN (414508)";
    }

    private function content_selection(int $start_from = null)
    {
        if ($this->_content_selection === null) {
            file_put_contents(ROOT . '/cli/content_selection.sql', $this->content_selection_final($start_from));
            if (($this->_content_selection = sqlsrv_query($this->conn, $this->content_selection_final($start_from), null, array( "Scrollable" => SQLSRV_CURSOR_CLIENT_BUFFERED ))) === false) {
                throw new Exception(json_encode(sqlsrv_errors()));
            }

        }
        return $this->_content_selection;
    }

    private $_content_selection = null;

    /**
     * Iterate through content_selection_final().
     * @return false|object|null
     * @throws Exception
     */
    public function content_fetch(int $start_from = null)
    {
        return sqlsrv_fetch_object($this->content_selection($start_from));
    }

    public function _sql_fetchAuthors()
    {
        $data = sqlsrv_query($this->conn, "SELECT
        *
      FROM [Liberal].[dbo].[authors]");
        $authors = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $authors[] = $row;
        }
        return !empty($authors) ? $authors : null;
    }

    public function _sql_fetchTags(int $offset = 0, int $limit = 100)
    {
        $data = sqlsrv_query($this->conn, "SELECT * FROM [Liberal].[dbo].[tags] ORDER BY id OFFSET " . (int) $offset . " ROWS FETCH NEXT " . (int) $limit . " ROWS ONLY");
        $tags = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $tags[] = $row;
        }
        return !empty($tags) ? $tags : null;
    }

    public function _sql_fetchArticlesTagsNames(int $article_id)
    {
        $data = sqlsrv_query($this->conn, "SELECT [tags].title_el FROM [Liberal].[dbo].[tags][tags], [Liberal].[dbo].[content_tags_inner][pivot_tag]
         WHERE [tags].id = [pivot_tag].cont_tag_tag_id
         AND [pivot_tag].cont_tag_content_id =" . $article_id);
        $tags = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $tags[] = $row['title_el'];
        }
        return !empty($tags) ? $tags : null;
    }

    public function _sql_fetchStocks(int $offset = 0, int $limit = 100)
    {
        $data = sqlsrv_query($this->conn, "SELECT
            *
          FROM [Liberal_stocks].[dbo].[ase_stock] ORDER BY ase_stock_id OFFSET " . (int) $offset . " ROWS FETCH NEXT " . (int) $limit . " ROWS ONLY");
        $stocks = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $stocks[] = $row;
        }
        return $stocks;
    }

    public function _sql_fetchLiveMarkets(int $offset = 0, int $limit = 100)
    {
        $data = sqlsrv_query($this->conn, "SELECT
            *
          FROM [Liberal].[dbo].[liveMarket] ORDER BY liveMarket_id OFFSET " . (int) $offset . " ROWS FETCH NEXT " . (int) $limit . " ROWS ONLY");
        $stocks = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $stocks[] = $row;
        }
        return $stocks;
    }

    public function _sql_fetchStockNames(int $offset = 0, int $limit = 1000)
    {
        $data = sqlsrv_query($this->conn, "SELECT DISTINCT [ase_stock_symbol] FROM [Liberal_stocks].[dbo].[ase_stock]");
        $stocks = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $stocks[] = $row;
        }
        return $stocks;
    }

    public function fetchStocksForArtcile($articleId)
    {
        $data = sqlsrv_query($this->conn, "SELECT DISTINCT eq.equity_name_gr
    FROM [Liberal].[dbo].[tag_equity] te
    JOIN [Liberal].[dbo].[content_tags_inner] ats on te.te_tag_id=ats.cont_tag_tag_id
    JOIN [Liberal].[dbo].[content] c on c.id=ats.cont_tag_content_id,
    [Liberal_stocks].dbo.equities eq
    WHERE eq.equity_id=te.te_equity_id AND c.id =" . $articleId);
        $stocks = [];
        while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
            $stocks[] = $row;
        }
        return $stocks;
    }
}
