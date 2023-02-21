<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */
namespace Core2\Model;
/**
 * Class Modules
 */
class Modules extends \Zend_Db_Table_Abstract {

	protected $_name = 'core_modules';

	/**
	 * @param string $expr
	 * @param array  $var
	 * @return null|\Zend_Db_Table_Row_Abstract
	 */
	public function exists($expr, $var = array()) {

		$sel = $this->select();
		if ($var) {
			$sel->where($expr, $var);
		} else {
			$sel->where($expr);
		}
		return $this->fetchRow($sel->limit(1));
	}

    /**
     * Получаем идентификаторы всех активных модулей
     *
     * @return array
     */
    public function getIds() {
        $sel = $this->select()->from($this->_name, ['m_id', 'module_id'])->where("visible='Y'");
        $res = $this->fetchAll($sel);
        $data = [];
        foreach ($res as $val) {
            $data[$val->m_id] = $val->module_id;
        }
        return $data;
    }


    /**
     * Получение записи по Id
     * @param int $id
     * @return \Zend_Db_Table_Row_Abstract|null
     */
    public function getRowById(int $id):? \Zend_Db_Table_Row_Abstract {

        $select = $this->select()->where("m_id = ?", $id);

        return $this->fetchRow($select);
    }


    /**
     * получаем список активных модулей
     * @return array
     */
	public function getModuleList() {

		$mods = $this->_db->fetchAll("
			SELECT m.*,
				   sm_id,
				   sm_name,
				   sm_key,
				   m.is_public
			FROM core_modules AS m
				LEFT JOIN core_submodules AS sm ON m.m_id = sm.m_id AND sm.visible = 'Y'
			WHERE m.visible = 'Y'
			ORDER BY m.seq, sm.seq
		");

		return $mods;
	}
}