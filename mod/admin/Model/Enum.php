<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */

class Enum extends Zend_Db_Table_Abstract {

	protected $_name = 'core_enum';
	private $_enum = array();

	public function exists($expr, $var = array())
	{
		$sel = $this->select()->where($expr, $var);
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

    public function getEnum($global_id) {
	    if (!isset($this->_enum[$global_id])) {
            $res = $this->_db->fetchAll("SELECT e2.id, e2.name, e2.custom_field, e2.is_default_sw, CASE e.is_active_sw WHEN 'N' THEN 'N' ELSE e2.is_active_sw END AS is_active_sw
									FROM core_enum AS e
									INNER JOIN core_enum AS e2 ON e.id=e2.parent_id
									WHERE e.global_id=?
									ORDER BY e2.seq", $global_id);
            $data = array();
            foreach ($res as $value) {
                $data[$value['id']] = array(
                    'value' => $value['name'],
                    'is_default' => ($value['is_default_sw'] == 'Y' ? true : false),
                    'is_active_sw' => $value['is_active_sw']
                );
                $data[$value['id']]['custom'] = array();
                if ($value['custom_field']) {
                    $temp = explode(":::", $value['custom_field']);
                    foreach ($temp as $val) {
                        $temp2 = explode("::", $val);
                        $data[$value['id']]['custom'][$temp2[0]] = isset($temp2[1]) ? $temp2[1] : '';
                    }
                }
            }
            $this->_enum[$global_id] = $data;
        }
        return $this->_enum[$global_id];
    }
}