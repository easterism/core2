<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */

class Modules extends Zend_Db_Table_Abstract {

	protected $_name = 'core_modules';

	public function exists($expr, $var = array())
	{
		$sel = $this->select();
		if ($var) {
			$sel->where($expr, $var);
		} else {
			$sel->where($expr);
		}
		return $this->fetchRow($sel->limit(1));
	}

    /**
     * получаем список активных модулей
     * @return array
     */
	public function getModuleList()
	{
		$mods = $this->_db->fetchAll("SELECT m.m_id, module_id, m.m_name, m.is_public, sm_id, sm_name, sm_key
								 FROM core_modules AS m
								 	  LEFT JOIN core_submodules AS sm ON m.m_id = sm.m_id AND sm.visible='Y'
								WHERE m.visible = 'Y' AND m.is_public = 'Y'
								ORDER BY m.seq, sm.seq");
		return $mods;
	}
}