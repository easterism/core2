<?php
namespace Core2\Classes\Table;
use Core2\Classes\Table;
use Laminas\Session\Container as SessionContainer;


require_once __DIR__ . '/../Table.php';
require_once 'Db/Select.php';



/**
 * Class Db
 * @package Core2\Classes\Table
 */
class Db extends Table {

    protected $table         = '';
    protected $primary_key   = '';
    protected $query         = '';
    protected $query_params  = '';
    protected $select        = null;
    protected $is_fetched    = false;
    protected $query_parts   = [];


    /**
     * @param string $resource
     */
    public function __construct(string $resource) {
        parent::__construct($resource);
    }


    /**
     * @return \Zend_Db_Select|null
     */
    public function getSelect():? \Zend_Db_Select {

        return $this->select;
    }


    /**
     * @return array|null
     */
    public function getQueryParts(): array {

        return $this->query_parts;
    }


    /**
     * @param string $table
     */
    public function setTable(string $table) {
        $this->table      = $table;
        $this->table_name = $table;

        // Из class.list
        // Нужно для удаления
        if ($this->table && $this->primary_key) {
            $sess = new SessionContainer('List');
            $tmp              = ! empty($sess->{$this->resource}) ? $sess->{$this->resource} : [];
            $tmp['deleteKey'] = "{$this->table}.{$this->primary_key}";
            $sess->{$this->resource} = $tmp;
        }
    }


    /**
     * @param string $key
     */
    public function setPrimaryKey(string $key) {
        $this->primary_key = $key;

        // Из class.list
        // Нужно для удаления
        if ($this->table && $this->primary_key) {
            $sess = new SessionContainer('List');
            $tmp              = ! empty($sess->{$this->resource}) ? $sess->{$this->resource} : [];
            $tmp['deleteKey'] = "{$this->table}.{$this->primary_key}";
            $sess->{$this->resource} = $tmp;
        }
    }


    /**
     * @param string $query
     * @param array  $params
     */
    public function setQuery(string $query, array $params = []) {
        $this->query        = $query;
        $this->query_params = $params;
    }


    /**
     * Получение данных из базы
     * @return Row[]
     * @throws \Zend_Db_Select_Exception
     */
    public function fetchData(): array {

        return $this->fetchRows();
    }


    /**
     * Получение данных из базы
     * @return Row[]
     * @throws \Zend_Db_Select_Exception
     */
    public function fetchRows(): array {

        if ( ! $this->is_fetched) {
            $this->is_fetched = true;

            if ($this->data instanceof \Zend_Db_Select) {
                $this->data_rows = $this->fetchDataSelect($this->data);

            } elseif ($this->data instanceof \Zend_Db_Table_Abstract) {
                $this->data_rows = $this->fetchDataTable($this->data);

            } elseif ($this->query) {
                $this->data_rows = $this->fetchDataQuery($this->query);
            }
        }

        return $this->data_rows;
    }


    /**
     * @param \Zend_Db_Table_Abstract $table
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    private function fetchDataTable(\Zend_Db_Table_Abstract $table): array {

        $select = $table->select();

        return $this->fetchDataSelect($select);
    }


    /**
     * @param \Zend_Db_Select $select
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    private function fetchDataSelect(\Zend_Db_Select $select): array {

        if ( ! empty($this->session->table->search)) {
            foreach ($this->session->table->search as $key => $value) {

                if (isset($this->search_controls[$key]) &&
                    $this->search_controls[$key] instanceof Search &&
                    ! empty($value)
                ) {
                    $field = $this->search_controls[$key]->getField();
                    $type  = $this->search_controls[$key]->getType();

                    if (strpos($field, '/*ADD_SEARCH*/') !== false) {
                        $field = str_replace("/*ADD_SEARCH*/", "ADD_SEARCH", $field);
                    }

                    switch ($type) {
                        case self::SEARCH_TEXT:
                            if (strpos($field, 'ADD_SEARCH') !== false) {
                                $quoted_value = $this->db->quote($value);
                                $select->where(str_replace("ADD_SEARCH", $quoted_value, $field));

                            } else {
                                $select->where("{$field} LIKE ?", "%{$value}%");
                            }
                            break;

                        case self::SEARCH_RADIO:
                        case self::SEARCH_TEXT_STRICT:
                        case self::SEARCH_SELECT:
                            if (strpos($field, 'ADD_SEARCH') !== false) {
                                $quoted_value = $this->db->quote($value);
                                $select->where(str_replace("ADD_SEARCH", $quoted_value, $field));

                            } else {
                                $select->where("{$field} = ?", $value);
                            }
                            break;

                        case self::SEARCH_DATE:
                        case self::SEARCH_DATETIME:
                        case self::SEARCH_NUMBER:
                            if (is_array($value)) {
                                if (strpos($field, 'ADD_SEARCH') !== false) {
                                    if ( ! empty($value[0]) || ! empty($value[1])) {
                                        $quoted_value1 = $this->db->quote($value[0]);
                                        $quoted_value2 = $this->db->quote($value[1]);

                                        $where = str_replace("ADD_SEARCH1", $quoted_value1, $field);
                                        $where = str_replace("ADD_SEARCH2", $quoted_value2, $where);

                                        $select->where($where);
                                    }

                                } else {
                                    if ($value[0] && $value[1]) {
                                        $where  = $this->db->quoteInto("{$field} BETWEEN ?", $value[0]);
                                        $where .= $this->db->quoteInto(" AND ? ", $value[1]);
                                        $select->where($where);

                                    } elseif ($value[0]) {
                                        $select->where("{$field} >= ?", $value[0]);

                                    } elseif ($value[1]) {
                                        $select->where("{$field} <= ?", $value[1]);
                                    }
                                }
                            }
                            break;

                        case self::SEARCH_CHECKBOX:
                        case self::SEARCH_MULTISELECT:
                            if (strpos($field, 'ADD_SEARCH') !== false) {
                                $quoted_value = $this->db->quote($value);
                                $select->where(str_replace("ADD_SEARCH", $quoted_value, $field));

                            } else {
                                $select->where("{$field} IN(?)", $value);
                            }
                            break;
                    }
                }
            }
        }

        if ( ! empty($this->session->table->filter)) {
            foreach ($this->session->table->filter as $key => $value) {

                if (isset($this->filter_controls[$key]) &&
                    $this->filter_controls[$key] instanceof Filter &&
                    ! empty($value)
                ) {
                    $field = $this->filter_controls[$key]->getField();
                    $type  = $this->filter_controls[$key]->getType();

                    if (strpos($field, '/*ADD_SEARCH*/') !== false) {
                        $field = str_replace("/*ADD_SEARCH*/", "ADD_SEARCH", $field);
                    }

                    switch ($type) {
                        case self::FILTER_TEXT:
                            if (strpos($field, 'ADD_SEARCH') !== false) {
                                $quoted_value = $this->db->quote($value);
                                $select->where(str_replace("ADD_SEARCH", $quoted_value, $field));

                            } else {
                                $select->where("{$field} LIKE ?", "%{$value}%");
                            }
                            break;

                        case self::FILTER_TEXT_STRICT:
                        case self::FILTER_RADIO:
                        case self::FILTER_SELECT:
                            if (strpos($field, 'ADD_SEARCH') !== false) {
                                $quoted_value = $this->db->quote($value);
                                $select->where(str_replace("ADD_SEARCH", $quoted_value, $field));

                            } else {
                                $select->where("{$field} = ?", $value);
                            }
                            break;

                        case self::FILTER_DATE:
                        case self::FILTER_DATETIME:
                        case self::FILTER_NUMBER:
                            if (is_array($value)) {
                                if (strpos($field, 'ADD_SEARCH') !== false) {
                                    if ( ! empty($value[0]) || ! empty($value[1])) {
                                        $quoted_value1 = $this->db->quote($value[0]);
                                        $quoted_value2 = $this->db->quote($value[1]);

                                        $where = str_replace("ADD_SEARCH1", $quoted_value1, $field);
                                        $where = str_replace("ADD_SEARCH2", $quoted_value2, $where);

                                        $select->where($where);
                                    }

                                } else {
                                    if ($value[0] && $value[1]) {
                                        $where  = $this->db->quoteInto("{$field} BETWEEN ?", $value[0]);
                                        $where .= $this->db->quoteInto(" AND ? ", $value[1]);
                                        $select->where($where);

                                    } elseif ($value[0]) {
                                        $select->where("{$field} >= ?", $value[0]);

                                    } elseif ($value[1]) {
                                        $select->where("{$field} <= ?", $value[1]);
                                    }
                                }
                            }
                            break;

                        case self::FILTER_CHECKBOX:
                            if (strpos($field, 'ADD_SEARCH') !== false) {
                                $quoted_value = $this->db->quote($value);
                                $select->where(str_replace("ADD_SEARCH", $quoted_value, $field));

                            } else {
                                $select->where("{$field} IN(?)", $value);
                            }
                            break;
                    }
                }
            }
        }


        //проверка наличия полей для последовательности и автора
        if ($this->table) {
            $table_columns = $this->db->fetchCol("
                SELECT column_name 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = ? 
                  AND table_name = ?
            ", [
                $this->getDbSchema(),
                $this->table
            ]);

            if (in_array('seq', $table_columns)) {
                $this->records_seq = true;
            }

            if (in_array('author', $table_columns) &&
                $this->checkAcl($this->resource, 'list_owner') &&
                ! $this->checkAcl($this->resource, 'list_all')
            ) {
                $auth = \Zend_Registry::get('auth');
                $select->where("author = ?", $auth->NAME);
            }
        }

        $records_per_page = $this->is_round_calc
            ? $this->records_per_page + 1
            : $this->records_per_page;

        $offset = ($this->current_page - 1) * $this->records_per_page;
        $select->limit((int)$records_per_page, (int)$offset);

        if (isset($this->session->table->order) &&
            $this->session->table->order &&
            isset($this->columns[$this->session->table->order - 1])
        ) {
            $column = $this->columns[$this->session->table->order - 1];

            if ($column instanceof Column && $column->isSorting()) {
                $order_type     = $this->session->table->order_type ?? 'ASC';
                $order_field    = $column->getField();
                $select_columns = $this->getColumns($select);

                if ( ! empty($select_columns[$order_field])) {
                    $select->reset('order');
                    $select->order("{$order_field} {$order_type}");
                }
            }
        }

        $this->select = clone $select;
        $select_sql   = (string)$select;

        if ($this->is_round_calc) {
            if (strpos($select_sql, ' SQL_CALC_FOUND_ROWS') !== false) {
                $select_sql = str_replace(' SQL_CALC_FOUND_ROWS', "", $select_sql);
            }

            $explain = $this->db->fetchAll('EXPLAIN ' . $select_sql);

            foreach ($explain as $value) {
                if ($value['rows'] > $this->records_total_round) {
                    $this->records_total_round = $value['rows'];
                }
            }

            $data_result = $this->db->fetchAll($select_sql);

            if (count($data_result) > $this->records_per_page) {
                $this->records_total      = $offset + $this->records_per_page;
                $this->records_total_more = true;
                unset($data_result[array_key_last($data_result)]);

            } else {
                $this->records_total = $offset + count($data_result);
            }

        } else {
            if (strpos($select_sql, ' SQL_CALC_FOUND_ROWS') === false) {
                $select_sql = preg_replace('~^(\s*SELECT\s+)~', "$1SQL_CALC_FOUND_ROWS ", $select_sql);
            }

            $data_result         = $this->db->fetchAll($select_sql);
            $this->records_total = (int)$this->db->fetchOne('SELECT FOUND_ROWS()');
        }


        $data_rows = [];
        if ( ! empty($data_result)) {
            foreach ($data_result as $row) {
                $data_rows[] = new Row($row);
            }
        }

        return $data_rows ?: [];
    }


    /**
     * Получение данных по запросу sql
     * @param $query
     * @return array
     */
    private function fetchDataQuery($query): array {

        $select = new Table\Db\Select($query);


        if ( ! empty($this->session->table) && ! empty($this->session->table->search)) {
            foreach ($this->session->table->search as $key => $search_value) {
                $search_column = $this->search_controls[$key];

                if ($search_column instanceof Search) {
                    $search_field = $search_column->getField();

                    if (strpos($search_field, '/*ADD_SEARCH*/') !== false) {
                        $search_field = str_replace("/*ADD_SEARCH*/", "ADD_SEARCH", $search_field);
                    }

                    switch ($search_column->getType()) {
                        case self::SEARCH_DATE:
                        case self::SEARCH_DATETIME:
                        case self::SEARCH_NUMBER:
                            if (strpos($search_field, 'ADD_SEARCH') !== false) {
                                if ( ! empty($value[0]) || ! empty($value[1])) {
                                    $quoted_value1 = $this->db->quote($search_value[0]);
                                    $quoted_value2 = $this->db->quote($search_value[1]);

                                    $where = str_replace("ADD_SEARCH1", $quoted_value1, $search_field);
                                    $where = str_replace("ADD_SEARCH2", $quoted_value2, $where);

                                    $select->addWhere($where);
                                }

                            } else {
                                if ( ! empty($search_value[0]) && empty($search_value[1])) {
                                    $quoted_value = $this->db->quote($search_value[0]);
                                    $select->addWhere("{$search_field} >= {$quoted_value}");

                                } elseif (empty($search_value[0]) && ! empty($search_value[1])) {
                                    $quoted_value = $this->db->quote($search_value[1]);
                                    $select->addWhere("{$search_field} <= {$quoted_value}");

                                } elseif ( ! empty($search_value[0]) && ! empty($search_value[1])) {
                                    $quoted_value1 = $this->db->quote($search_value[0]);
                                    $quoted_value2 = $this->db->quote($search_value[1]);
                                    $select->addWhere("{$search_field} BETWEEN {$quoted_value1} AND {$quoted_value2}");
                                }
                            }
                            break;

                        case self::SEARCH_TEXT_STRICT:
                        case self::SEARCH_RADIO:
                        case self::SEARCH_SELECT:
                            if ($search_value != '') {
                                $quoted_value = $this->db->quote($search_value);

                                if (strpos($search_field, 'ADD_SEARCH') !== false) {
                                    $select->addWhere(str_replace("ADD_SEARCH", $quoted_value, $search_field));
                                } else {
                                    $select->addWhere("{$search_field} = {$quoted_value}");
                                }
                            }
                            break;

                        case self::SEARCH_CHECKBOX:
                        case self::SEARCH_MULTISELECT:
                            if ( ! empty($search_value)) {
                                $quoted_value = $this->db->quote($search_value);

                                if (strpos($search_field, 'ADD_SEARCH') !== false) {
                                    $select->addWhere(str_replace("ADD_SEARCH", $quoted_value, $search_field));
                                } else {
                                    $select->addWhere("{$search_field} IN ({$quoted_value})");
                                }
                            }
                            break;

                        case self::SEARCH_TEXT:
                            if ($search_value != '') {
                                if (strpos($search_field, 'ADD_SEARCH') !== false) {
                                    $quoted_value = $this->db->quote($search_value);
                                    $select->addWhere(str_replace("ADD_SEARCH", $quoted_value, $search_field));

                                } else {
                                    $quoted_value = $this->db->quote('%' . $search_value . '%');
                                    $select->addWhere("{$search_field} LIKE {$quoted_value}");
                                }
                            }
                            break;
                    }
                }
            }
        }


        if ( ! empty($this->session->table) && ! empty($this->session->table->filter)) {
            foreach ($this->session->table->filter as $key => $filter_value) {
                if ( ! isset($this->filter_controls[$key])) {
                    continue;
                }

                $filter_column = $this->filter_controls[$key];

                if ($filter_column instanceof Filter) {
                    $filter_field = $filter_column->getField();

                    if (strpos($filter_field, '/*ADD_SEARCH*/') !== false) {
                        $filter_field = str_replace("/*ADD_SEARCH*/", "ADD_SEARCH", $filter_field);
                    }

                    switch ($filter_column->getType()) {
                        case self::FILTER_DATE:
                        case self::FILTER_DATETIME:
                        case self::FILTER_NUMBER:
                            if (strpos($search_field, 'ADD_SEARCH') !== false) {
                                if ( ! empty($value[0]) || ! empty($value[1])) {
                                    $quoted_value1 = $this->db->quote($filter_value[0]);
                                    $quoted_value2 = $this->db->quote($filter_value[1]);

                                    $where = str_replace("ADD_SEARCH1", $quoted_value1, $filter_field);
                                    $where = str_replace("ADD_SEARCH2", $quoted_value2, $where);

                                    $select->addWhere($where);
                                }

                            } else {
                                if ( ! empty($filter_value[0]) && empty($filter_value[1])) {
                                    $quoted_value = $this->db->quote($filter_value[0]);
                                    $select->addWhere("{$filter_field} >= {$quoted_value}");

                                } elseif (empty($filter_value[0]) && ! empty($filter_value[1])) {
                                    $quoted_value = $this->db->quote($filter_value[1]);
                                    $select->addWhere("{$filter_field} <= {$quoted_value}");

                                } elseif ( ! empty($filter_value[0]) && ! empty($filter_value[1])) {
                                    $quoted_value1 = $this->db->quote($filter_value[0]);
                                    $quoted_value2 = $this->db->quote($filter_value[1]);
                                    $select->addWhere("{$filter_field} BETWEEN {$quoted_value1} AND {$quoted_value2}");
                                }
                            }
                            break;

                        case self::FILTER_TEXT_STRICT:
                        case self::FILTER_RADIO:
                        case self::FILTER_SELECT:
                            if ($filter_value != '') {
                                $quoted_value = $this->db->quote($filter_value);

                                if (strpos($filter_field, 'ADD_SEARCH') !== false) {
                                    $select->addWhere(str_replace("ADD_SEARCH", $quoted_value, $filter_field));
                                } else {
                                    $select->addWhere("{$filter_field} = {$quoted_value}");
                                }
                            }
                            break;

                        case self::FILTER_CHECKBOX:
                            if ( ! empty($filter_value)) {
                                $quoted_value = $this->db->quote($filter_value);

                                if (strpos($filter_field, 'ADD_SEARCH') !== false) {
                                    $select->addWhere(str_replace("ADD_SEARCH", $quoted_value, $filter_field));
                                } else {
                                    $select->addWhere("{$filter_field} IN ({$quoted_value})");
                                }
                            }
                            break;

                        case self::FILTER_TEXT:
                            if ($filter_value != '') {
                                if (strpos($filter_field, 'ADD_SEARCH') !== false) {
                                    $quoted_value = $this->db->quote($filter_value);
                                    $select->addWhere(str_replace("ADD_SEARCH", $quoted_value, $filter_field));

                                } else {
                                    $quoted_value = $this->db->quote('%' . $filter_value . '%');
                                    $select->addWhere("{$filter_field} LIKE {$quoted_value}");
                                }
                            }
                            break;
                    }
                }
            }
        }

        //проверка наличия полей для последовательности и автора
        if ($this->table) {
            $table_columns = $this->db->fetchCol("
                SELECT column_name 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = ? 
                  AND table_name = ?
            ", [
                $this->getDbSchema(),
                $this->table
            ]);

            if (in_array('seq', $table_columns)) {
                $this->records_seq = true;
            }

            if (in_array('author', $table_columns) &&
                $this->checkAcl($this->resource, 'list_owner') &&
                ! $this->checkAcl($this->resource, 'list_all')
            ) {
                $auth         = \Zend_Registry::get('auth');
                $quoted_value = $this->db->quote($auth->NAME);
                $select->addWhere("author = {$quoted_value}");
            }
        }


        if (isset($this->session->table->order) &&
            $this->session->table->order &&
            isset($this->columns[$this->session->table->order - 1])
        ) {
            $column = $this->columns[$this->session->table->order - 1];

            if ($column instanceof Column && $column->isSorting()) {
                $order_type     = $this->session->table->order_type ?? 'ASC';
                $order_field    = $column->getField();
                $select_columns = $select->getSelectColumns();

                if ( ! empty($select_columns[$order_field])) {
                    $select->setOrderBy("{$order_field} {$order_type}");
                }
            }
        }


        $records_per_page = $this->is_round_calc
            ? $this->records_per_page + 1
            : $this->records_per_page;

        $offset = ($this->current_page - 1) * $this->records_per_page;
        $select->setLimit($records_per_page, $offset);


        if ( ! $this->table) {
            $this->setTable($select->getTable());
        }

        $this->query_parts = $select->getSqlParts();

        $select_sql = $select->getSql();

        if ($this->is_round_calc) {
            if (strpos($select_sql, ' SQL_CALC_FOUND_ROWS') !== false) {
                $select_sql = str_replace(' SQL_CALC_FOUND_ROWS', "", $select_sql);
            }

            $explain = $this->db->fetchAll('EXPLAIN ' . $select_sql, $this->query_params);

            foreach ($explain as $value) {
                if ($value['rows'] > $this->records_total_round) {
                    $this->records_total_round = $value['rows'];
                }
            }

            $result = $this->db->fetchAll($select_sql, $this->query_params);

            if (count($result) > $this->records_per_page) {
                $this->records_total      = $offset + $this->records_per_page;
                $this->records_total_more = true;
                unset($result[array_key_last($result)]);

            } else {
                $this->records_total = $offset + count($result);
            }

        } else {
            if (strpos($select_sql, ' SQL_CALC_FOUND_ROWS') === false) {
                $select_sql = preg_replace('~^(\s*SELECT\s+)~', "$1SQL_CALC_FOUND_ROWS ", $select_sql);
            }

            $result = $this->db->fetchAll($select_sql, $this->query_params);
            $this->records_total = $this->db->fetchOne("SELECT FOUND_ROWS()");
        }


        $data_rows = [];
        if ( ! empty($result)) {
            foreach ($result as $key => $row) {
                $data_rows[$key] = new Row($row);
            }
        }

        return $data_rows ?: [];
    }


    /**
     * Список колонок
     * @param \Zend_Db_Select $select
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    private function getColumns(\Zend_Db_Select $select): array {

        $columns = $select->getPart('columns');
        $result  = [];

        if ( ! empty($columns)) {
            foreach ($columns as $column) {

                $alias = $column[2] ?: $column[1];

                if ($alias instanceof \Zend_Db_Expr) {
                    $alias = $this->db->quoteIdentifier($alias);
                }

                if ($column[1] instanceof \Zend_Db_Expr) {
                    $name = $this->db->quoteIdentifier($column[1]);
                } else {
                    $name = $this->db->quoteIdentifier($column[0]) . '.' . $this->db->quoteIdentifier($column[1]);
                }

                $result[$alias] = $name;
            }
        }

        return $result;
    }
}