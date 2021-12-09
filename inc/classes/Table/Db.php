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

    protected $table       = '';
    protected $primary_key = '';
    protected $query        = '';
    protected $query_params = '';


    /**
     * @param string $resource
     */
    public function __construct(string $resource) {
        parent::__construct($resource);
    }


    /**
     * @param string $table
     */
    public function setTable(string $table) {
        $this->table = $table;

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
     */
    public function fetchData(): array {

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
     */
    private function fetchDataTable(\Zend_Db_Table_Abstract $table): array {

        $select = $table->select();

        return $this->fetchDataSelect($select);
    }


    /**
     * @param \Zend_Db_Select $select
     * @return array
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

                    switch ($type) {
                        case self::SEARCH_TEXT:
                            $select->where("{$field} LIKE ?", "%{$value}%");
                            break;

                        case self::SEARCH_TEXT_STRICT:
                            $select->where("{$field} = ?", $value);
                            break;

                        case self::SEARCH_DATE:
                        case self::SEARCH_DATETIME:
                        case self::SEARCH_NUMBER:
                            if (is_array($value)) {
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
                            break;

                        case self::SEARCH_SELECT:
                            $select->where("{$field} IN(?)", $value);
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


        $offset = $this->current_page == 1 ? 0 : ($this->current_page - 1) * $this->records_per_page;
        $select->limit((int)$this->records_per_page, (int)$offset);


        if (isset($this->session->table->order) &&
            $this->session->table->order &&
            isset($this->columns[$this->session->table->order - 1])
        ) {
            $order_type = $this->session->table->order_type ?? 'ASC';
            $select->reset('order');


            $order_field = $this->columns[$this->session->table->order - 1]->getField();
            $select->order("{$order_field} {$order_type}");
        }

        $data_rows           = [];
        $data_result         = $this->db->fetchAll($select);
        $this->records_total = (int)$this->db->fetchRow('SELECT FOUND_ROWS() AS count')['count'];

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

                    switch ($search_column->getType()) {
                        case self::SEARCH_DATE:
                        case self::SEARCH_DATETIME:
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
                            break;

                        case self::SEARCH_TEXT_STRICT:
                        case self::SEARCH_RADIO:
                        case self::SEARCH_SELECT:
                            if ($search_value != '') {
                                $quoted_value = $this->db->quote($search_value);
                                $select->addWhere("{$search_field} = {$quoted_value}");
                            }
                            break;

                        case self::SEARCH_CHECKBOX:
                        case self::SEARCH_MULTISELECT:
                            if ( ! empty($search_value)) {
                                $quoted_value = $this->db->quote($search_value);
                                $select->addWhere("{$search_field} IN ({$quoted_value})");
                            }
                            break;

                        case self::SEARCH_TEXT:
                            if ($search_value != '') {
                                $quoted_value = $this->db->quote('%' . $search_value . '%');
                                $select->addWhere("{$search_field} LIKE {$quoted_value}");
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



        if (isset($this->session->table->order) && $this->session->table->order) {
            $select->setOrderBy(($this->session->table->order + 1) . ' ' . $this->session->table->order_type);
        }


        if ($this->current_page == 1) {
            $select->setLimit($this->records_per_page);

        } elseif ($this->current_page > 1) {
            $offset = ($this->current_page - 1) * $this->records_per_page;
            $select->setLimit($this->records_per_page, $offset);
        }

        if ( ! $this->table) {
            $this->setTable($select->getTable());
        }


        $sql = $select->getSql();

        if ($this->round_record_count) {
            $explain = $this->db->fetchAll('EXPLAIN ' . $sql, $this->query_params);
            $this->records_total = 0;
            foreach ($explain as $value) {
                if ($value['rows'] > $this->records_total) {
                    $this->records_total = $value['rows'];
                }
            }
            $result = $this->db->fetchAll($sql, $this->query_params);
        } else {
            $result = $this->db->fetchAll("SELECT SQL_CALC_FOUND_ROWS " . substr(trim($sql), 6), $this->query_params);
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
     * @param $select
     * @return array
     */
    private function getColumns($select): array {

        $columns = $select->getPart('columns');
        $result  = [];

        if ( ! empty($columns)) {
            foreach ($columns as $column) {

                $alias = $column[2] ?: $column[1];

                if ($alias instanceof \Zend_Db_Expr) {
                    $alias = $this->db->quoteIdentifier($alias);
                }

                $name = $this->db->quoteIdentifier($column[0]) . '.' . $this->db->quoteIdentifier($column[1]);

                $result[$alias] = $name;
            }
        }

        return $result;
    }
}