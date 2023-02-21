<?php
namespace Core2\Model;

/**
 * Class Users
 */
class Settings extends \Zend_Db_Table_Abstract {

	protected $_name = 'core_settings';

    /**
     * Получаем значение одного поля
     *
     * @param $field
     * @param $expr
     * @param array $var
     * @return string
     */
    public function fetchOne($field, $expr, $var = array())
    {
        $sel = $this->select();
        if ($var) {
            $sel->where($expr, $var);
        } else {
            $sel->where($expr);
        }
        return $this->fetchRow($sel)->$field;
    }

    /**
     * @param $expr
     * @param array $var
     * @return null|\Zend_Db_Table_Row_Abstract
     */
    public function exists($expr, $var = array()) {
        $sel = $this->select()->where($expr, $var);

        return $this->fetchRow($sel->limit(1));
    }

    /**
     * @return mixed
     */
    public function getSystem() {
        $sel = $this->select()->where("visible = 'Y' AND is_custom_sw = 'N'");
        return $this->fetchAll($sel);
    }

    /**
     * @return mixed
     */
    public function getCustom() {
        $sel = $this->select()->where("visible = 'Y' AND is_custom_sw = 'Y'");
        return $this->fetchAll($sel);
    }

}