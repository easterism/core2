<?php
namespace Core2;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\DatabaseException;

class ClickHouse extends \Common {

    private static $_db = null;

    private $_count_all = 0;
    private $_sql = '';

    public function __construct()
    {
        parent::__construct();
        $this->getDb();
        return $this;
    }

    /**
     * @throws Exception
     */
    private function getDb() {

        if (!$conf = $this->moduleConfig->clickhouse) {
            throw new \Exception('Не заполнены настройки для подключения к Clickhouse ' . $this->module);
        }
        $db = new Client($conf->toArray());
        $db->settings()->https();
        $db->setTimeout(1);      // 1 second , support only Int value
        $db->setTimeout(10);       // 10 seconds
        $db->setConnectTimeOut(5); // 5 seconds
        $db->ping(true); // if can`t connect throw exception
        $db->database($conf->database);

        self::$_db = $db;
    }

    /**
     * @throws \Exception
     */
    public function select($request) {

        return self::$_db->select($request)->rawData()['data'];
    }

    public function countAll()
    {
        return $this->_count_all;
    }

    public function quoteInto($text, $value, $type = null, $count = null)
    {
        if ($count === null) {
            return str_replace('?', $this->quote($value, $type), $text);
        } else {
            return implode($this->quote($value, $type), explode('?', $text, $count + 1));
        }
    }

    public function quote($value, $type = null)
    {
        if (is_array($value)) {
            foreach ($value as &$val) {
                $val = $this->quote($val, $type);
            }
            return implode(', ', $value);
        }

        return $this->_quote($value);
    }

    protected function _quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        }
        return "'" . addcslashes($value, "\000\n\r\\'\"\032") . "'";
    }

    /**
     * @throws \Exception
     */
    public function fetchOne($request) {

        if (strpos($request, "FOUND_ROWS()") > 0) {
            $this->_count_all = self::$_db->select($this->_sql)->countAll();
            return $this->_count_all;
        }
        return self::$_db->select($request)->fetchOne()->rawData()['data'];
    }


    /**
     * @throws \Exception
     */
    public function fetchRow($request) {

        $data = self::$_db->select($request)->fetchRow();
        return $data;
    }

    public function fetchAll($SQL, $bind = []) {
        $SQL = str_replace("SQL_CALC_FOUND_ROWS", "", $SQL);
        if ($bind) {
            $SQL = str_replace("?", "%s", $SQL);
            $SQL = sprintf($SQL, $bind);
        }
        $this->_sql = $SQL;
        $data = self::$_db->select($SQL)->rows();
        return $data;
    }

    /**
     * Вставлям строку
     * @param $table
     * @param $data
     * @return mixed
     */
    public function insert($table, $data)
    {
        $keys = array_keys($data);
        $vals = array_values($data);
        $stat = self::$_db->insert($table,
            [
                $vals
            ],
            $keys
        );
        return $stat;
    }
}