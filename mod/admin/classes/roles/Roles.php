<?php
namespace Core2;

require_once DOC_ROOT . 'core2/inc/classes/class.list.php';
require_once DOC_ROOT . 'core2/inc/classes/class.edit.php';
require_once DOC_ROOT . 'core2/inc/classes/class.tab.php';
require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater3.php';

/**
 * Class Roles
 * @package Core2
 */
class Roles extends \Common {

    private $app = "index.php?module=admin&action=roles";
    private $_role;


    /**
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    private function table() {

        $tab = new \tabs('roles');
        $tab->beginContainer($this->translate->tr("Роли и доступ"));

        if ($tab->activeTab == 1) {
            if (isset($_GET['edit']) && $_GET['edit'] != '') {
                $this->printJs("core2/mod/admin/assets/js/role.js");
                $edit = new \editTable('roles');
                $edit->SQL = $this->db->quoteInto("
                    SELECT id,
                           name,
                           description,
                           position,
                           access
                    FROM core_roles
                    WHERE id = ?
                ", $_GET['edit']);

                $edit->addControl($this->translate->tr("Название:"), "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
                $edit->addControl($this->translate->tr("Краткое описание:"), "TEXT", "size=\"60\"", "", "");
                $edit->addControl($this->translate->tr("Позиция в иерархии:"), "NUMBER", "maxlength=\"3\" size=\"2\"", "", "", true);

                $modules = $this->db->fetchAll("
                    SELECT * 
                    FROM (
                        (SELECT m_id, 
                                m_name, 
                                module_id, 
                                m.seq AS seq, 
                                '' AS seq2, 
                                m.access_add
                         FROM core_modules AS m
                         WHERE m.visible = 'Y')
                        
                        UNION ALL 
                        
                        (SELECT sm_id AS m_id,
                                CONCAT(m_name, ' / ', sm_name) AS m_name,
                                CONCAT(m.module_id, '-', s.sm_key) AS module_id,
                                m.seq AS seq, 
                                s.seq AS seq2,
                                s.access_add
                         FROM core_submodules AS s
                             INNER JOIN core_modules AS m ON m.m_id = s.m_id AND m.visible = 'Y'
                         WHERE sm_id > 0
                           AND s.visible = 'Y')
                       ) AS a
                    ORDER BY seq, seq2

                ");

                $html = '<table>';


                foreach ($modules as $module) {
                    $tpl = new \Templater3("core2/mod/admin/assets/html/role_access.html");

                    if ($module['access_add']) {
                        $access_add_data = @unserialize(base64_decode($module['access_add']));

                        if ( ! empty($access_add_data)) {
                            foreach ($access_add_data as $keyAD => $valueAD) {
                                if ($keyAD) {
                                    $tpl->custom->assign('NAME_ACTION', $keyAD);
                                    $tpl->custom->assign('TYPE_ID',     $keyAD);
                                    $tpl->custom->assign('MODULE_ID',   $module['module_id']);
                                    $tpl->custom->reassign();
                                }
                            }
                        }
                    }

                    $html .= '<tr><td class="roleModules" id="' . $module['module_id'] . '"><span class="roleModulesClick">' . $module['m_name'] . '</span></td>'.
                        '<td>' . str_replace("MODULE_ID", $module['module_id'], $tpl->render()) . '</td>'.
                        '</tr>';

                }
                $html .= '</table>';
                if ($_GET['edit']) {
                    $acl = json_encode(unserialize($this->dataRoles->find($_GET['edit'])->current()->access));

                    $html .= '<script>ro.setDefault(' . $acl . ')</script>';
                } else {
                    $html .= '<script>ro.setDefaultNew()</script>';
                }
                $edit->addControl($this->translate->tr("Доступ к модулям:"), "CUSTOM", $html);

                $edit->back = $this->app;
                $edit->addParams('is_copy', 0);
                $edit->addButton($this->_('Копировать'), "$('[name=is_copy]').val(1);this.form.onsubmit();$('[name=is_copy]').val(0)");
                $edit->save("xajax_saveRole(xajax.getFormValues(this.id))");

                $edit->showTable();
            }
            $list = new \listTable('roles');

            $list->table = "core_roles";

            $list->SQL = "
                SELECT `id`,
                       `name`,
                       description,
                       position,
                       is_active_sw
                FROM `core_roles` 
                ORDER BY position
            ";

            $list->addColumn($this->translate->tr("Роль"), "", "TEXT");
            $list->addColumn($this->translate->tr("Описание"), "", "TEXT");
            $list->addColumn($this->translate->tr("Иерархия"), "1%", "TEXT");
            $list->addColumn("", "1%", "STATUS");

            $list->paintCondition = "'TCOL_04' == 'N'";
            $list->paintColor     = "ffffee";

            $list->addURL    = $this->app . "&edit=0";
            $list->editURL   = $this->app . "&edit=TCOL_00";
            $list->deleteKey = "core_roles.id";

            $list->showTable();

        } elseif ($tab->activeTab == 2) {

        }
        $tab->endContainer();
    }


    /**
     * вывод
     */
    public function dispatch() {
        if ($this->_role) {
            $this->edit();
        } else {
            $this->table();
        }
    }
}