<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */
namespace Core2\Model;

class Roles extends \Zend_Db_Table_Abstract {

	protected $_name = 'core_roles';

	public function fetchOne($field, $expr, $var = array())
	{
		$sel = $this->select();
		if ($var) {
			$sel->where($expr, $var);
		} else {
			$sel->where($expr);
		}
        $res = $this->fetchRow($sel);
        return $res ? $res->$field : null;
	}

    /**
     * Получение записи по Id
     * @param int $id
     * @return \Zend_Db_Table_Row_Abstract|null
     */
    public function getRowById(int $id):? \Zend_Db_Table_Row_Abstract {

        $select = $this->select()->where("id = ?", $id);

        return $this->fetchRow($select);
    }

}