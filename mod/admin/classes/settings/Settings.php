<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 10.10.2017
 * Time: 1:24
 */

namespace Core2;


class Settings extends \Common
{
    private $app = "index.php?module=admin&action=settings";
    private $_setting;

    /**
     * Получени записи базы данных по id
     * значение -1 используется для редактирования системных параметров
     * @param $id
     */
    public function edit($id) {
        if ($id > 0) {
            $this->get($id);
        } else {
            $this->_setting = -1;
        }
    }

    /**
     * Создание записи баз дынных (без сохранения)
     */
    public function create() {
        $this->_setting = $this->dataSettings->createRow();
    }

    /**
     * Управление параметрами Настройки системы
     */
    public function stateSystem() {
        $edit = new \editTable('set');

        $types     = array('text', 'date', 'textarea', 'date2', 'datetime', 'datetime2', 'money', 'number');
        $edit->SQL = array(array('id' => 1));
        $settings  = $this->dataSettings->getSystem();
        foreach ($settings as $setting) {
            $edit->SQL[0][$setting['code']] = $setting['value'];

            $type = $setting['type'] && in_array(strtolower($setting['type']), $types)
                ? $setting['type']
                : 'text';
            $edit->addControl($setting['system_name'] . ":", $type, 'size="30"');
        }


        if ($this->_setting == -1) {
            $edit->addButton($this->translate->tr("Отменить"), "load('$this->app')");
            $edit->back = $this->app;
            $edit->save("xajax_saveSettings(xajax.getFormValues(this.id))");

        } else {
            $edit->readOnly = true;
            $edit->addButton($this->translate->tr("Редактировать"), "load('$this->app&edit=yes')");
        }
        //$edit->addControl("Язык по умолчанию:", "PROTECTED");
        //$edit->addControl("Валюта по умолчанию:", "PROTECTED");

        //$edit->back = $app;
        //$edit->save("xajax_saveModule(xajax.getFormValues(this.id), {m_name:'req',module_id:'req',visible:'req'})");
        $edit->firstColWidth = '290px';
        $edit->showTable();
    }

    /**
     * Управление Дополнительными параметрами
     */
    public function stateAdd() {
        if ($this->_setting) {
            $edit = new \editTable('custom');
            $edit->SQL  = "SELECT id,
								 `code`,
								 `value`,
								 system_name,
								 visible
							FROM core_settings 
							WHERE is_custom_sw='Y'
							AND id='{$this->_setting->id}'";
            if ($_GET['edit']) {
                $edit->addControl($this->translate->tr("Ключ:"), "PROTECT");
            } else {
                $edit->addControl($this->translate->tr("Ключ:"), "EDIT", "", "", "", true);
            }
            $edit->addControl($this->translate->tr("Значение:"), "EDIT", "size=\"60\"");
            $edit->addControl($this->translate->tr("Описание:"), "EDIT", "size=\"60\"");
            $edit->addButtonSwitch('visible', $this->_setting->id ? $this->dataSettings->exists("visible='Y' AND id=?", $this->_setting->id) : '');
            $edit->firstColWidth = '250px';
            $edit->back = $this->app . "&tab_settings=2";
            $edit->save("xajax_saveCustomSettings(xajax.getFormValues(this.id))");
            $edit->showTable();
        }
        $list = new \listTable('custom');
        $list->table = 'core_settings';

        $list->SQL = "SELECT `id`,
                           `code`,
                           value,
                           system_name,
                           visible
                    FROM `core_settings`
                    WHERE is_custom_sw = 'Y'
                    ORDER BY seq";
        $list->addColumn($this->translate->tr("Ключ"), "", "TEXT");
        $list->addColumn($this->translate->tr("Значение"), "", "TEXT");
        $list->addColumn($this->translate->tr("Описание"), "", "TEXT");
        $list->addColumn("", "1%", "STATUS_INLINE", "core_settings.visible");

        $list->paintCondition	= "'TCOL_04' == 'N'";
        $list->paintColor		= "ffffee";

        $list->addURL 			= $this->app . "&edit=0&tab_settings=2";
        $list->editURL 			= $this->app . "&edit=TCOL_00&tab_settings=2";
        $list->deleteKey		= "core_settings.id";

        $list->getData();
        foreach ($list->data as $k => $datum) {
            $list->data[$k][2] = $this->trimAction($datum[2]);
        }

        $list->showTable();
    }

    /**
     * Управленеи персональными параметрами
     */
    public function statePersonal() {
        if ($this->_setting) {
            $edit = new \editTable('personal');
            $edit->SQL  = "SELECT id,
								 `code`,
								 `value`,
								 system_name,
								 visible
							FROM core_settings
							WHERE is_personal_sw='Y'
							AND id='{$this->_setting->id}'";
            if ($_GET['edit']) {
                $edit->addControl($this->translate->tr("Ключ:"), "PROTECT");
            } else {
                $edit->addControl($this->translate->tr("Ключ:"), "EDIT", "", "", "", true);
            }
            $edit->addControl($this->translate->tr("Значение:"), "EDIT");
            $edit->addControl($this->translate->tr("Описание:"), "EDIT", "size=\"60\"");
            $edit->addButtonSwitch('visible', $this->_setting->id ? $this->dataSettings->exists("visible='Y' AND id=?", $this->_setting->id) : '');
            $edit->firstColWidth = '250px';
            $edit->back = $this->app . "&tab_settings=3";
            $edit->save("xajax_savePersonalSettings(xajax.getFormValues(this.id))");
            $edit->showTable();
        }
        $list = new \listTable('personal');

        $list->SQL = "SELECT `id`,
                         `code`,
                         value,
                         system_name,
                         visible
                    FROM `core_settings`
                    WHERE is_personal_sw='Y'
                    ";
        $list->addColumn($this->translate->tr("Ключ"), "", "TEXT");
        $list->addColumn($this->translate->tr("Значение"), "", "TEXT");
        $list->addColumn($this->translate->tr("Описание"), "", "TEXT");
        $list->addColumn("", "1%", "STATUS_INLINE", "core_settings.visible");

        $list->paintCondition	= "'TCOL_04' == 'N'";
        $list->paintColor		= "ffffee";

        $list->addURL 			= $this->app . "&edit=0&tab_settings=3";
        $list->editURL 			= $this->app . "&edit=TCOL_00&tab_settings=3";
        $list->deleteKey		= "core_settings.id";

        $list->showTable();

    }

    /**
     * Получени записи базы данных по id
     * @param $id
     * @throws \Exception
     */
    private function get($id) {
        $this->_setting = $this->dataSettings->find($id)->current();
        if (!$this->_setting) throw new \Exception(404);
    }

    /**
     * Ограничение длины значения для отображения в таблице
     * @param $data
     * @return string
     */
    private function trimAction($data)
    {
        $r = mb_substr($data, 0, 180, "UTF-8");
        if ($r != $data) {
            $r .= "   ...";
        } else {
            $r = $data;
        }
        return $r;
    }

}