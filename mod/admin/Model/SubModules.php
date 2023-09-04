<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */
namespace Core2\Model;

class SubModules extends \Zend_Db_Table_Abstract {

	protected $_name = 'core_submodules';
	protected $_referenceMap = array(
		'Module' => array(
			'columns'       => 'm_id',
			'refTableClass' => 'Modules'
		)
	);

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

    public function fetchFields($fields, $expr, $var = array()) {
        $sel = $this->select()->from($this->_name, $fields);
        if ($var) {
            $sel->where($expr, $var);
        } else {
            $sel->where($expr);
        }
        return $this->fetchAll($sel);
    }


    /**
     * @param int $module_id
     * @return int
     */
    public function getCountByModuleId(int $module_id): int {

        $select = $this->select()
            ->from($this->_name, ['count' => 'COUNT(*)'])
            ->where("m_id = ?", $module_id);

        $row = $this->fetchRow($select);

        return $row ? (int)$row['count'] : 0;
    }


    public function getSubmodules($module) {
        $data = $this->_db->fetchPairs("
            SELECT sm_key, sm_name
            FROM core_submodules
            WHERE m_id=(SELECT m_id FROM core_modules WHERE module_id=?)
        ", $module);
        return $data;
    }
}