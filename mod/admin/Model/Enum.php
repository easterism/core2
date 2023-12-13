<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */
namespace Core2\Model;

class Enum extends \Zend_Db_Table_Abstract {

	protected $_name = 'core_enum';
	private $_enum   = [];


    /**
     * Добавление записи в справочник
     * @param string $global_id
     * @param string $value
     * @param array  $custom
     * @param array  $options
     * @return int
     * @throws \Exception
     */
    public function createItem (string $global_id, string $value, array $custom = [], array $options = []) : int {

        $select = $this->select()
            ->where('global_id = ?', $global_id);
        $enum = $this->fetchRow($select);

        if (empty($enum)) {
            throw new \Exception(sprintf('Справочник %s не найден', $global_id));
        }

        $custom_fields = [];

        foreach ($custom as $key => $item) {
            if (is_scalar($item)) {
                $custom_fields[] = "{$key}::{$item}";
            }
        }


        $select = $this->select()
            ->from($this->_name, ['max_seq' => new \Zend_Db_Expr('MAX(seq)')])
            ->where('parent_id = ?', $enum->id);

        $items = $this->fetchRow($select);
        $seq   = 1 + (int)($items ? $items->max_seq : 0);

        $data = $this->createRow([
            'parent_id'     => $enum->id,
            'name'          => $value,
            'is_default_sw' => $options['is_default'] ?? 'N',
            'is_active_sw'  => $options['is_active_sw'] ?? 'Y',
            'lastuser'      => $options['lastuser'] ?? null,
            'seq'           => $seq,
            'custom_field'  => $custom_fields ? implode(':::', $custom_fields) : null
        ]);

        return (int)$data->save();
    }


    /**
     * Получает id записи справочника по значению
     *
     * @param string $name
     * @return int
     * @throws \Zend_Db_Table_Exception
     */
    public function getEnumIdByValue(string $name) : int {
        $select = $this->select()
            ->where('name = ?', $name);
        $item = $this->fetchRow($select);
        return ($item->id);
    }


    /**
     * Обновление custom_field в одной записи справочника
     * @param int $enum_item_id
     * @param array $enum_custom_fields
     */
    public function setCustomFields (int $enum_item_id, array $enum_custom_fields): void {
       $where = $this->getAdapter()->quoteInto('id = ?', $enum_item_id);
       $this->update([
            'custom_field' => $enum_custom_fields ? implode(':::', $enum_custom_fields) : null,
        ], $where);
    }


    /**
     * @param $expr
     * @param $var
     * @return \Zend_Db_Table_Row_Abstract|null
     */
	public function exists($expr, $var = []) {
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

    public function fetchPairs($fields, $expr, $var = array()) {
        $res = $this->fetchFields($fields, $expr, $var = array())->toArray();
        $data = [];
        foreach ($res as $item) {
            $key = current($item);
            $val = next($item);
            $data[$key] = $val;
        }
        return $data;
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

        if ( ! isset($this->_enum[$global_id])) {
            $res  = $this->_db->fetchAll("
                SELECT e2.id, 
                       e2.name, 
                       e2.custom_field, 
                       e2.is_default_sw, 
                       CASE e.is_active_sw 
                           WHEN 'N' THEN 'N' 
                           ELSE e2.is_active_sw 
                       END AS is_active_sw
				FROM core_enum AS e
				    INNER JOIN core_enum AS e2 ON e.id = e2.parent_id
				WHERE e.global_id = ?
				ORDER BY e2.seq
            ", $global_id);

            $data = [];
            foreach ($res as $value) {
                $data[$value['id']]           = [
                    'value'        => $value['name'],
                    'is_default'   => ($value['is_default_sw'] == 'Y' ? true : false),
                    'is_active_sw' => $value['is_active_sw']
                ];
                $data[$value['id']]['custom'] = [];
                if ($value['custom_field']) {
                    $temp = explode(":::", $value['custom_field']);
                    foreach ($temp as $val) {
                        $temp2                                   = explode("::", $val);
                        $data[$value['id']]['custom'][$temp2[0]] = isset($temp2[1]) ? $temp2[1] : '';
                    }
                }
            }
            $this->_enum[$global_id] = $data;
        }

        return $this->_enum[$global_id];
    }

    public function getEnumById($enum_id) {

        if ( ! isset($this->_enum[$enum_id])) {
            $res  = $this->_db->fetchAll("
                SELECT e.id, 
                       e.name, 
                       e.custom_field
				FROM core_enum AS e
				WHERE e.id = ?
            ", $enum_id);

            $data = [];
            foreach ($res as $value) {
                $data[$value['id']]           = [
                    'value'        => $value['name']
                ];
                $data[$value['id']]['custom_field'] = [];
                if ($value['custom_field']) {
                    $temp = explode(":::", $value['custom_field']);
                    foreach ($temp as $val) {
                        $temp2                                   = explode("::", $val);
                        $data[$value['id']]['custom_field'][$temp2[0]] = isset($temp2[1]) ? $temp2[1] : '';
                    }
                }
            }
            $this->_enum[$enum_id] = $data;
        }

        return $this->_enum[$enum_id];
    }
}