<?php
namespace Core2\Classes\Table\Db;


/**
 * Class Select
 */
class Select {

    /**
     * @var array
     */
    private $sql = [];

    /**
     * @var array
     */
    private $sub_queries = [];


    /**
     * @param string $sql Текст SQL запроса
     */
    public function __construct(string $sql) {
        $this->parse($sql);
    }


    /**
     * Получение колонок
     * @return array
     */
    public function getSelectColumns(): array {

        $columns = [];

        if ( ! empty($this->sql['SELECT'])) {
            $select_explode = explode(',', $this->sql['SELECT']);

            if ( ! empty($select_explode)) {
                foreach ($select_explode as $select_column) {
                    if (preg_match('~\s*(.*)\s+AS\s+[\'"`]?([\w\d_ ]+)[\'"`]?\s*$~mi', trim($select_column), $matches)) {
                        $field = $matches[1];

                        if (preg_match('~^(\[\d*\])$~', $field, $matches_sub_query)) {
                            $field = $this->sub_queries[$matches_sub_query[1]] ?? $field;
                        }

                        $columns[$matches[2]] = $field;

                    } elseif (preg_match('~\s*[\'"`]?([\w\d_ \.]+)[\'"`]?\s*$~mi', trim($select_column), $matches)) {
                        $alias = $matches[1];

                        if (mb_strpos($alias, '.') !== false) {
                            $alias = mb_substr($alias, mb_strrpos($alias, '.') + 1);
                        }

                        $columns[$alias] = $matches[1];
                    }
                }
            }
        }

        return $columns;
    }


    /**
     * @param string|array $where
     */
    public function addWhere($where) {

        $where = is_array($where) ? implode(' AND ', $where) : $where;

        if ( ! empty($this->sql['WHERE'])) {
            $this->sql['WHERE'] .= ' AND ' . $where;
        } else {
            $this->sql['WHERE'] = $where;
        }
    }



    /**
     * @param string $order_by
     */
    public function setOrderBy($order_by) {

        $this->sql['ORDER BY'] = $order_by;
    }


    /**
     * @param $limit
     * @param $offset
     */
    public function setLimit($limit, $offset = null) {

        $this->sql['LIMIT'] = (is_int($offset) ? "{$offset}, " : '') . $limit;
    }


    /**
     * @return string|bool
     */
    public function getTable() {

        $matches = array();
        preg_match('~^(`[a-zA-Z0-9_ ]+`|[a-zA-Z0-9_]+)~i', trim($this->sql['FROM']), $matches);

        return  ! empty($matches[1]) ? $matches[1] : false;
    }



    /**
     * @return string
     */
    public function getSql() {

        $operators = [];
        foreach ($this->sql as $operator => $query) {
            if ($query) $operators[] = $operator . ' ' . $query;
        }

        $sql = implode(' ', $operators);

        if ( ! empty($this->sub_queries)) {
            foreach ($this->sub_queries as $hash => $query) {
                $sql = str_replace($hash, $query, $sql);
            }
        }

        return $sql;
    }


    /**
     * @param string $sql Текст SQL запроса
     */
    private function parse($sql) {

        $sub_queries = [];

        preg_match_all('~(\((?:(?>[^()]+)|(?R))*\))~i', $sql, $sub_queries);

        if ( ! empty($sub_queries[1])) {
            foreach ($sub_queries[1] as $sub_query) {
                if (preg_match('~^\(\s*SELECT\s~i', $sub_query)) {
                    $query_hash = hash('crc32b', $sub_query);
                    $query_hash = "[{$query_hash}]";
                    $sql = str_replace($sub_query, $query_hash, $sql);
                    $this->sub_queries[$query_hash] = $sub_query;
                }
            }
        }

        if (preg_match('~([\s\)]UNION(?:\s+ALL|))~is', $sql)) {
            $this->sql['SELECT'] = '*';
            $this->sql['FROM'] = "({$sql}) AS tmp_tbl";

        } else {
            $select_match = array();
            preg_match('~^(?:\s*SELECT\s+)(.*?(?=\s+FROM\s+|\sORDER\s+BY\s|\sLIMIT\s|$))~is', $sql, $select_match);
            $this->sql['SELECT'] = ! empty($select_match[1]) ? $select_match[1] : '';

            $from_match = array();
            preg_match('~(?:\s+FROM\s+)(.*?(?=\sWHERE\s|\sGROUP\s+BY\s|\sORDER\s+BY\s|\sLIMIT\s|$))~is', $sql, $from_match);
            $this->sql['FROM'] = ! empty($from_match[1]) ? $from_match[1] : '';

            $where_match = array();
            preg_match('~(?:\s+WHERE\s+)(.*?(?=\sGROUP\s+BY\s|\sORDER\s+BY\s|\sLIMIT\s|$))~is', $sql, $where_match);
            $this->sql['WHERE'] = ! empty($where_match[1]) ? $where_match[1] : '';

            $group_by_match = array();
            preg_match('~(?:\s+GROUP\s+BY\s+)(.*?(?=\sHAVING\s|\sORDER\s+BY\s|\sLIMIT\s|$))~is', $sql, $group_by_match);
            $this->sql['GROUP BY'] = ! empty($group_by_match[1]) ? $group_by_match[1] : '';

            $having_match = array();
            preg_match('~(?:\s+HAVING\s+)(.*?(?=\sORDER\s+BY\s|\sLIMIT\s|$))~is', $sql, $having_match);
            $this->sql['HAVING'] = ! empty($having_match[1]) ? $having_match[1] : '';

            $order_by_match = array();
            preg_match('~(?:\s+ORDER\s+BY\s+)(.*?(?=\sLIMIT\s|$))~is', $sql, $order_by_match);
            $this->sql['ORDER BY'] = ! empty($order_by_match[1]) ? $order_by_match[1] : '';

            $limit_match = array();
            preg_match('~(?:\s+LIMIT\s+)(.*)~is', $sql, $limit_match);
            $this->sql['LIMIT'] = ! empty($limit_match[1]) ? $limit_match[1] : '';
        }
    }
}