<?php
namespace Core2;

require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/class.list.php';
require_once DOC_ROOT . 'core2/inc/classes/class.edit.php';
require_once DOC_ROOT . 'core2/inc/classes/class.tab.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater3.php';

/**
 * Class Roles
 * @package Core2
 */
class Enum extends \Common {

    private $app = "index.php?module=admin&action=enum";
    private $html;


    /**
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function newEnum() {
        $edit = new \editTable('enum');
        //$edit->firstColWidth = 200;
        $edit->SQL = "SELECT id,
                         name,
                         global_id,
                         custom_field
                    FROM core_enum
                    WHERE 1=2";
        $edit->addControl($this->translate->tr("Название справочника:"), "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
        $edit->addControl($this->translate->tr("Идентификатор справочника:"), "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);

        $tpl = new \Templater3("core2/mod/admin/assets/html/custom_enum_field.html");
        $enums = $this->dataEnum->fetchFields(array('global_id', 'name'), "parent_id IS NULL")->toArray();
        $tpl->fillDropDown('yyy', $enums, '');
        $custom_field = $tpl->render();

        $custom = '<div id="xxx"></div>
			<div><span id="new_attr" class="newFieldEnum btn btn-link btn-sm" onclick="en.newEnumField()">' . $this->translate->tr("Новое поле") . '</span></div>';
        $edit->addControl($this->translate->tr("Дополнительные поля:"), "CUSTOM", $custom);
        $edit->addButtonSwitch('is_active_sw', 1);

        $edit->back = $this->app;
        $edit->addButton($this->translate->tr("Вернуться к списку справочников"), "load('$this->app')");
        $edit->save("xajax_saveEnum(xajax.getFormValues(this.id))");

        ob_start();
        $edit->showTable();
        echo '<div style="display:none" id="hid_custom">' . str_replace(array('[VALUE]',
                '[TYPE]',
                '[ENUM]',
                'id="yyy"'),
                array('',
                    '',
                    '',
                    '',
                ),
                $custom_field) . '</div>';
        return ob_get_clean();
    }

    /**
     *
     * @param $enum_id
     */
    public function editEnum($enum_id) {
        $edit = new \editTable('enum');
        //$edit->firstColWidth = 200;
        $edit->SQL = $this->db->quoteInto("
				SELECT id,
					 name,
					 global_id,
					 custom_field
				FROM core_enum
				WHERE id = ?
				  AND parent_id IS NULL
			", $enum_id);
        $edit->addControl($this->translate->tr("Название справочника:"), "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
        $edit->addControl($this->translate->tr("Идентификатор:"), "PROTECTED");
        $list_custom = "";
        $tpl = new \Templater3("core2/mod/admin/assets/html/custom_enum_field.html");
        $enums = $this->dataEnum->fetchPairs(array('global_id', 'name'), "parent_id IS NULL");
        $tpl->fillDropDown('yyy', $enums, '');
        $custom_field = $tpl->render();

        $res           = $this->dataEnum->find($enum_id)->current()->custom_field;
        $custom_fields = $res ? unserialize(base64_decode($res)) : [];

        if (is_array($custom_fields) && $custom_fields) {
            foreach ($custom_fields as $value) {
                $list_custom .= str_replace(
                    array('[VALUE]',
                        'value="' . $value['type'] . '"',
                        'value="' . $value['enum'] . '"',
                        'id="yyy" style="display:none"',
                        'name="list[]" style="display:none"'
                    ),
                    array($value['label'],
                        'value="' . $value['type'] . '" selected="selected"',
                        'value="' . $value['enum'] . '" selected="selected"',
                        ((($value['type'] == 2) || ($value['type'] == 3)) ? '' : 'style="display:none"'),
                        (($value['type'] == 6) ? 'name="list[]" value="' . $value['list'] . '"' : 'name="list[]" style="display:none"')
                    ),
                    $custom_field
                );
            }
        }

        $custom = '<div id="xxx">' . $list_custom . '</div>
			<div><span id="new_attr" class="newFieldEnum btn btn-link btn-sm" onclick="en.newEnumField()">' . $this->translate->tr("Новое поле") . '</span></div>';
        $edit->addControl($this->translate->tr("Дополнительные поля:"), "CUSTOM", $custom);
        $edit->addButtonSwitch('is_active_sw', $this->dataEnum->exists("is_active_sw = 'Y' AND id=?", $enum_id));

        $edit->back = $this->app;
        $edit->addButton($this->translate->tr("Вернуться к списку справочников"), "load('$this->app')");
        $edit->save("xajax_saveEnum(xajax.getFormValues(this.id))");

        ob_start();
        $edit->showTable();
        echo '<div style="display:none" id="hid_custom">' . str_replace(array('[VALUE]',
                '[TYPE]',
                '[ENUM]',
                'id="yyy"'),
                array('',
                    '',
                    '',
                    '',
                ),
                $custom_field) . '</div>';
        return ob_get_clean();

    }

    /**
     * Новое значение справочника
     * @param $enum_id
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function newEnumValue($enum_id) {
        $res = $this->dataEnum->find($enum_id)->current()->custom_field;
        $custom_fields = $res ? unserialize(base64_decode($res)) : [];
        $edit = new \editTable('enumxxxur');

        $fields_sql = '';
        $edit->addControl($this->translate->tr("Значение:"), "TEXT", "maxlength=\"128\" size=\"60\"", "", "", true);
        if (is_array($custom_fields) && count($custom_fields)) {
            $fields_sql = "\n";
            foreach ($custom_fields as $key => $val) {
                $label       = '';
                $default     = '';
                $fields_sql .= "'{$label}' AS id_$key,\n";

                if ($val['type'] == 1) $type = 'TEXT';
                elseif ($val['type'] == 2) {
                    $type = 'LIST';
                    $edit->selectSQL[] = $this->getEnumDropdown($val['enum'], true, true);
                    $default = $this->db->fetchOne("
                            SELECT e.name
                            FROM core_enum AS e
                            WHERE e.is_default_sw = 'Y'
                              AND (SELECT id
                                   FROM core_enum
                                   WHERE global_id = ?
                                   LIMIT 1) = e.parent_id
                        ", $val['enum']);

                }
                elseif ($val['type'] == 3) {
                    $type = 'CHECKBOX';
                    $edit->selectSQL[] = $this->getEnumDropdown($val['enum'], true);
                }
                elseif ($val['type'] == 4) $type = 'TEXTAREA';
                elseif ($val['type'] == 5) {
                    $type = 'RADIO';
                    $edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет');
                } elseif ($val['type'] == 6) {
                    $type              = 'LIST';
                    $temp = explode(',', $val['list']);
                    $arr = array();
                    foreach ($temp as $value) {
                        $arr[$value] = $value;
                    }
                    $edit->selectSQL[] = $arr;
                }
                $edit->addControl($val['label'], $type, '', '', $default);
            }
            $fields_sql = rtrim($fields_sql);
            $edit->addParams("custom_fields", base64_encode(serialize($custom_fields)));
        }

        $edit->SQL = "SELECT id,
                       name, $fields_sql
                       is_default_sw,
                       is_active_sw,
                       parent_id
                FROM core_enum
                WHERE id = 0";

        $edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет');
        $edit->addControl($this->translate->tr("По умолчанию:"), "RADIO", "", "", "N");
        $edit->selectSQL[] = array('Y' => 'вкл.', 'N' => 'выкл.');
        $edit->addControl($this->translate->tr("Статус:"), "RADIO", "", "", "Y");
        $edit->addControl("", "HIDDEN", "", "", $enum_id, true);

        $edit->back = $this->app . "&edit=" . $enum_id;
        $edit->addButton($this->translate->tr("Отменить"), "load('{$this->app}&edit={$enum_id}')");
        $edit->save("xajax_saveEnumValue(xajax.getFormValues(this.id))");
        ob_start();
        $edit->showTable();
        return ob_get_clean();
    }


    /**
     * @param $enum_id
     * @param $value_id
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function editEnumValue($enum_id, $value_id) {

        $add           = (int)$value_id;
        $res           = $this->dataEnum->find($enum_id)->current()->custom_field;
        $custom_fields = $res ? unserialize(base64_decode($res)) : [];
        $edit          = new \editTable('enumxxxur');

        $res2       = $this->dataEnum->find($add)->current()?->custom_field;
        $arr_fields = [];

        /* Формирование массива кастомных полей из строки */

        if ( ! empty($res2)) {
            $name_val = explode(":::", $res2);
            foreach ($name_val as $v) {
                $temp                 = explode("::", $v);
                $arr_fields[$temp[0]] = isset($temp[1]) ? $temp[1] : "";
            }
        }

        $fields_sql = '';
        $edit->addControl($this->translate->tr("Значение:"), "TEXT", "maxlength=\"128\" size=\"60\"", "", "", true);

        if (is_array($custom_fields) && count($custom_fields)) {
            $fields_sql = "\n";
            foreach ($custom_fields as $key => $val) {

                $fields_sql .= "NULL AS id_$key,\n";

                if ($val['type'] == 1) {
                    $type = 'TEXT';

                } elseif ($val['type'] == 2) {
                    $type = 'LIST';
                    $edit->selectSQL[] = $this->getEnumDropdown($val['enum'], true, true);

                    // $default = $this->db->fetchOne("
                    //     SELECT e.name
                    //     FROM core_enum AS e
                    //     WHERE e.is_default_sw = 'Y'
                    //       AND (SELECT id
                    //            FROM core_enum
                    //            WHERE global_id = ?
                    //            LIMIT 1) = e.parent_id
                    // ", $val['enum']);

                } elseif ($val['type'] == 3) {
                    $type = 'CHECKBOX';
                    $edit->selectSQL[] = $this->getEnumDropdown($val['enum'], true);

                } elseif ($val['type'] == 4) {
                    $type = 'TEXTAREA';

                } elseif ($val['type'] == 5) {
                    $type = 'RADIO';
                    $edit->selectSQL[] = ['Y' => 'да', 'N' => 'нет'];

                } elseif ($val['type'] == 6) {
                    $type              = 'LIST';
                    $temp = explode(',', $val['list']);
                    $arr = [];
                    foreach ($temp as $value) {
                        $arr[$value] = $value;
                    }
                    $edit->selectSQL[] = $arr;
                }

                $field_value = isset($arr_fields[$val['label']]) ? $arr_fields[$val['label']] : '';
                $edit->addControl($val['label'], $type, '', '', $field_value);
            }

            $fields_sql = rtrim($fields_sql);
            $edit->addParams("custom_fields", base64_encode(serialize($custom_fields)));
        }

        $edit->SQL = $this->db->quoteInto("
            SELECT id,
                   name, {$fields_sql}
                   is_default_sw,
                   is_active_sw,
                   parent_id
            FROM core_enum
            WHERE id = ?
        ", $value_id);


        $edit->addControl($this->translate->tr("По умолчанию:"), "RADIO", "", "", "N"); $edit->selectSQL[] = ['Y' => 'да', 'N' => 'нет'];
        $edit->addControl($this->translate->tr("Статус:"), "RADIO", "", "", "Y"); $edit->selectSQL[] = ['Y' => 'вкл.', 'N' => 'выкл.'];
        $edit->addControl("", "HIDDEN", "", "", $enum_id, true);

        $edit->back = $this->app . "&edit=" . $enum_id;
        $edit->addButton($this->translate->tr("Отменить"), "load('{$this->app}&edit={$enum_id}')");
        $edit->save("xajax_saveEnumValue(xajax.getFormValues(this.id))");

        return $edit->render();
    }

    /**
     * Перечень значений справочника
     * @param $enum_id
     * @return false|string
     * @throws \Exception
     */
    public function listEnumValues($enum_id) {

        $res = $this->dataEnum->find($enum_id)->current()->custom_field;
        $custom_fields = $res ? unserialize(base64_decode($res)) : [];

        //ENUM dtl list
        $list = new \listTable('enumxxx3'.$enum_id);
        $list->table = "core_enum";
        $list->addSearch($this->translate->tr('Значение'), 'name', 'TEXT');

        $list->addColumn($this->translate->tr("Значение"),     "",    "TEXT");

        $fields_sql = '';
        if (is_array($custom_fields) && count($custom_fields)) {
            $fields_sql = "\n";
            foreach ($custom_fields as $key => $val) {
                $fields_sql .= "'id_{$val['label']}',\n";
                $list->addColumn($val['label'], "", "TEXT", '', '', false);
            }
            $fields_sql = rtrim($fields_sql);

        }

        $list->SQL = $this->db->quoteInto("
                SELECT id,
                       name, {$fields_sql}
                       CASE is_default_sw WHEN 'Y' THEN 'Да' ELSE 'Нет' END AS is_default_sw,
                       seq,
                       is_active_sw,
                       custom_field
                FROM core_enum
                WHERE parent_id = ? /*ADD_SEARCH*/
                ORDER BY seq, name
            ", $_GET['edit']);

        $list->addColumn($this->translate->tr("По умолчанию"), "120", "TEXT");
        $list->addColumn($this->translate->tr("Очередность"),  "105", "TEXT");
        $list->addColumn("", 								   "1%",  "STATUS_INLINE", 'core_enum.is_active_sw');

        $list->paintCondition	= "'TCOL_03' == 'N'";
        $list->paintColor		= "ffffee";

        $list->addURL 			= $this->app . "&edit=$enum_id&newvalue";
        $list->editURL 			= $this->app . "&edit=$enum_id&editvalue=TCOL_00";
        $list->deleteKey		= "core_enum.id";

        $list->getData();
        foreach ($list->data as $key => $row) {

            $fields_values = explode(":::", (string)end($row));
            if ( ! empty($fields_values)) {
                foreach ($fields_values as $fields_value) {
                    if (strpos($fields_value, '::') !== false) {
                        $temp = explode("::", $fields_value);
                        if (($k = array_search('id_' . $temp[0], $row))) {
                            $list->data[$key][$k] = $temp[1];
                        }
                    }
                }

                if ( ! empty($custom_fields)) {
                    $i = 2;
                    foreach ($custom_fields as $field) {
                        switch ($field['type']) {
                            case '5':
                                $list->data[$key][$i] = $list->data[$key][$i] == 'Y'
                                    ? 'Да'
                                    : ($list->data[$key][$i] == 'N' ? 'Нет' : '');
                                break;
                        }

                        $i++;
                    }
                }
            }

            // очищает незаполненые поля
            for ($i = 2; $i < count($row) - 2; $i++) {
                if ( ! empty($list->data[$key][$i]) && strpos($list->data[$key][$i], 'id_') === 0) {
                    $list->data[$key][$i] = '';
                }
            }
        }
        return $list->render();
    }

    /**
     * Список системных справочников
     */
    public function listEnum() {
        $list = new \listTable('enum');
        $list->addSearch($this->translate->tr('Идентификатор'),        'global_id', 'TEXT');
        $list->addSearch($this->translate->tr('Название справочника'), 'name',      'TEXT');

        $list->SQL = "
				SELECT id,
					   global_id,
					   name,
					   CASE WHEN custom_field IS NOT NULL THEN 'Да' END AS custom,
					   (SELECT COUNT(1) FROM core_enum WHERE parent_id = e.id) AS co,
					   is_active_sw
				FROM core_enum AS e
				WHERE parent_id IS NULL /*ADD_SEARCH*/
				ORDER BY `name`
			";
        $list->addColumn($this->translate->tr("Идентификатор"), 	   "120", "TEXT");
        $list->addColumn($this->translate->tr("Название справочника"), "",    "TEXT");
        $list->addColumn($this->translate->tr("Дополнительные поля"),  "165", "TEXT");
        $list->addColumn($this->translate->tr("Значений"), 			   "90",  "NUMBER");
        $list->addColumn("", 										   "1%",  "STATUS_INLINE", 'core_enum.is_active_sw');

        $list->paintCondition	= "'TCOL_04' == 'N'";
        $list->paintColor		= "fafafa";

        $list->addURL 			= $this->app . "&new";
        $list->editURL 			= $this->app . "&edit=TCOL_00";
        $list->deleteKey		= "core_enum.id";

        return $list->render();
    }

}