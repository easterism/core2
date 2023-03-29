<?php
namespace Core2;

require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/class.list.php';
require_once DOC_ROOT . 'core2/inc/classes/class.edit.php';
require_once DOC_ROOT . 'core2/inc/classes/class.tab.php';
require_once DOC_ROOT . 'core2/inc/classes/Panel.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater2.php';
require_once DOC_ROOT . 'core2/inc/classes/Table/Data.php';

require_once "Gitlab.php";

use Laminas\Session\Container as SessionContainer;
use Core2\Mod\Admin;
use Core2\Classes\Table;

/**
 * Class Modules
 * @property \SubModules $dataSubModules
 * @property \Modules    $dataModules
 */
class Modules extends \Common  {

    private $app = "index.php?module=admin&action=modules";

    /**
     * Загрузка архива с модулем
     * или загрузка с gitlab
     * @param Int $avail_id
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function getAvailableEdit(Int $avail_id) {
        $edit = new \editTable('mod_available');
        /* Добавление нового модуля */
        if ( ! $avail_id || $avail_id < 0) {
            if (empty($this->config->php) ||
                empty($this->config->php->path) ||
                empty( exec('which php'))
            ) {
                $edit->error = " - В conf.ini не задан параметр php.path, проверка синтакса php файлов будет пропущена!";
            }

            $edit->SQL = "
                SELECT id,
                       name
                FROM core_available_modules
                WHERE id = 0
            ";

            if ($avail_id < 0) {
                $modal_data = [
                    'title'    => 'Gitlab репозиторий',
                    'url'      => 'index.php?module=admin&action=modules&page=table_gitlab',
                    'size'     => 'large',
                    'text'     => '',
                ];

                $edit->addControl("GitLab релиз", "MODAL2", $modal_data, '', '', true);

            } else {
                $edit->addControl("Файл архива(.zip)", "XFILE_AUTO", "", "", "");
            }

            $edit->classText['SAVE'] = $this->_("Загрузить");
            $edit->back              = $this->app . "&tab=available";
            $edit->save("xajax_saveAvailModule(xajax.getFormValues(this.id))");
            $edit->showTable();

        }
        elseif (!empty($avail_id)) { // Инфа о модуле
            $edit = new \editTable('modules_install');
            $edit->SQL = "SELECT 1";

            $res = $this->db->fetchRow("SELECT name, version, readme, install_info
                                            FROM core_available_modules
                                            WHERE id=?", $avail_id);
            $title = "<h2><b>" . $this->_("Инструкция по установке модуля") . "</b></h2>";
            $content = $res['readme'];
            $inf = unserialize($res['install_info']);

            $modId   = isset($inf['install']['module_id'])   ? $inf['install']['module_id']   : '';
            $modVers = isset($inf['install']['version'])     ? $inf['install']['version']     : '';
            $modName = isset($inf['install']['module_name']) ? $inf['install']['module_name'] : '';
            $is_module = $this->db->fetchRow("SELECT m_id 
                                                    FROM core_modules 
                                                    WHERE module_id = ? 
                                                      AND version = ?
                                                ", array(
                $modId,
                $modVers
            ));

            if (empty($content)) {
                $content = $title . "<br>" . $this->_("Информация по установке отсутствует");
            } else {
                $content = $title . $content;
            }

            echo $content;
            if (!is_array($is_module)) {
                $tpl = new \Templater2("core2/html/" . THEME . "/buttons.tpl");
                $tpl->touchBlock("install_button");
                $tpl->assign("modName", $modName);
                $tpl->assign("modVers", $modVers);
                $tpl->assign("modInstall", $avail_id);
                $edit->addButtonCustom($tpl->parse());
                $edit->readOnly = true;
            }

            $edit->addButton($this->_("Отмена"), "load('$this->app&tab=available')");
            $edit->addButtonCustom('<input class="button" type="button" value="' . $this->_("Скачать файлы модуля") . '" onclick="loadPDF(\'index.php?module=admin&action=modules&tab=available&download_mod=' . $avail_id . '\')">');
            $edit->showTable();

        }
    }

    /**
     * Список доступных для установки модулей
     * @throws \Exception
     */
    public function getAvailable() {
        //список доступных модулей
        $list = new \listTable('mod_available');

        $list->addSearch($this->_("Имя модуля"),      '`name`',  	'TEXT');
        $list->addSearch($this->_("Идентификатор"),	'module_id','TEXT');

        $list->SQL = "SELECT 1";
        //        $list->extOrder = true;
        $list->addColumn($this->_("Имя модуля"), "200px", "TEXT", "", "", "");
        $list->addColumn($this->_("Идентификатор"), "200px", "TEXT", "", "", "");
        $list->addColumn($this->_("Описание"), "", "TEXT", "", "", "");
        $list->addColumn($this->_("Зависимости"), "200px", "BLOCK", "", "", "");
        $list->addColumn($this->_("Версия"), "150px", "BLOCK", "", "", "");
        $list->addColumn($this->_("Автор"), "150px", "TEXT", "", "", "");
        $list->addColumn($this->_("Системный"), "50px", "TEXT", "", "", "");
        $list->addColumn($this->_("Действие"), "66", "BLOCK", 'align=center', "", "");
        $list->getData();
        //поиск
        $where_search = '';
        $ss = new SessionContainer('Search');
        $ss = $ss->main_mod_available;
        if (!empty($ss['search'])) {
            foreach ($ss['search'] as $k => $s) {
                $s = trim($s);
                if (!empty($s)) {
                    if ($k == 0) {
                        $where_search .= " AND `name` LIKE '%" . mb_strtolower($s, 'utf-8') . "%' ";
                    } elseif ($k == 1) {
                        $where_search .= " AND `module_id` LIKE '%" . mb_strtolower($s, 'utf-8') . "%' ";
                    }
                }
            }
        }


        $copy_list = $this->db->fetchAll("
            SELECT id,
                   `name`,
                   module_id,
                   module_group,
                   descr,
                   NULL AS deps,
                   version,
                   NULL AS author,
                   NULL AS ia_sys_sw,
                   install_info
            FROM core_available_modules
            WHERE 1=1 {$where_search}
            ORDER BY module_group, `name`
        ");

        if (!empty($copy_list)) {
            $allMods = array();
            $tmp = $this->db->fetchAll("SELECT module_id, version FROM core_modules");
            foreach ($tmp as $t) {
                $allMods[$t['module_id']] = $t['version'];
            }
        }

        $tmp = array();
        $_GET['_page_mod_available'] = !empty($_GET['_page_mod_available']) ? (int)$_GET['_page_mod_available'] : 0;

        $install = new InstallModule();
        foreach ($copy_list as $row) {
            $arr[0] = $row['id'];
            $arr[1] = ($row['module_group'] ? "<b>{$row['module_group']}</b> " : '') . $row['name'];
            $arr[2] = $row['module_id'];
            $arr[3] = $row['descr'];
            $mData = unserialize(htmlspecialchars_decode($row['install_info']));
            $arr[4] = '';
            //зависимости модулей
            $Inf = !empty($mData['install']['dependent_modules']) ? $mData['install']['dependent_modules'] : array();
            $deps = array();
            if (
                !empty($Inf['m']['module_name']) || !empty($Inf['m'][0]['module_name']) //новая версия
                || !empty($Inf['m']) //старая версия
            ) {
                if (
                    !empty($Inf['m']['module_name'])  //новая версия
                    || !is_array($Inf['m']) //старая версия
                ) {
                    $tmp2 = $Inf['m'];
                    $Inf['m'] = array();
                    $Inf['m'][] = $tmp2;
                }
                //старая версия
                foreach ($Inf['m'] as $k => $dep_value) {
                    if (is_string($dep_value)) {
                        $Inf['m'][$k] = array('module_id' => $dep_value);
                    }
                }
                //проверяем в соответствии с условиямив се ли нужные модули установлены
                $deps = $install->getNeedToInstallDependedModList($Inf['m']);
            } elseif (!empty($Inf)) {
                $deps[] = "<span style=\"color: red;\">" . $this->_("Неверный install.xml") . "</span>";
            }
            $arr[4] = implode("<br>", $deps);

            $arr[5] = $row['version'];
            $arr[6] = isset($mData['install']['author']) ? $mData['install']['author'] : '';
            $arr[7] = isset($mData['install']['module_system']) && $mData['install']['module_system'] == 'Y' ? "Да" : "Нет";

            //кнопка установки
            $arr[8] = "";
            if (!empty($allMods[$row['module_id']]) && $row['version'] <= $allMods[$row['module_id']]) {
                //				$arr[8] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Уже установлен\" border=\"0\"/>";
            } elseif (!empty($deps)) {
                //				$arr[8] = "<img onclick=\"alert('Сначала установите модули: " . implode(", ", $needToInstall) . "')\" src=\"core2/html/".THEME."/img/box_out.png\" border=\"0\" title=\"Установить\"/>";
                $arr[8] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Требуется установка дополнительных модулей\" border=\"0\"/>";
            } else {
                $arr[8] = "<img  onclick=\"installModule('{$row['name']}', 'v{$row['version']}', '{$row['id']}', {$_GET['_page_mod_available']});\" src=\"core2/html/".THEME."/img/box_out.png\" border=\"0\" title=\"Установить\"/>";
            }
            $arr[8] .= "<img onclick=\"modules.download('{$row['name']}', 'v{$row['version']}', '{$row['id']}');\" src=\"core2/html/".THEME."/img/disk.png\" border=\"0\" title=\"скачать архив\"/>";

            $tmp[$row['module_id']][$row['version']] = $arr;
        }
        //смотрим есть-ли разные версии одного мода
        //если есть, показываем последнюю, осатльные в спойлер
        $copy_list = array();
        foreach ($tmp as $module_id => $row) {
            krsort($row, SORT_NATURAL);
            $max_ver = key($row);
            $copy_list[$module_id] = $row[$max_ver];
            unset($row[$max_ver]);
            if (!empty($row)) {
                $copy_list[$module_id][5] .= " <a href=\"\" onclick=\"$('.mod_available_{$module_id}').toggle(); return false;\">" . $this->_("Предыдущие версии") . "</a><br>";
                $copy_list[$module_id][5] .= "<table width=\"100%\" class=\"mod_available_{$module_id}\" style=\"display: none;\"><tbody>";
                foreach ($row as $version => $row) {
                    $copy_list[$module_id][5] .= "
                            <tr>
                                <td style=\"border: 0px; padding: 0px;\">{$version}</td>
                                <td style=\"border: 0px; text-align: right; padding: 0px;\">{$row[8]}</td>
                            </tr>
                        ";
                }
                $copy_list[$module_id][5] .= "</tbody></table>";
            }
        }
        //пагинация
        $ss = new SessionContainer('Search');
        $ss = $ss->main_mod_available;
        if (!empty($ss['count_mod_available'])) {
            $per_page = empty($ss['count_mod_available']) ? 1 : (int)$ss['count_mod_available'];
        }
        $list->recordsPerPage = $per_page;

        $page = empty($_GET['_page_mod_available']) ? 1 : (int)$_GET['_page_mod_available'];
        $from = ($page - 1) * $per_page;
        $to = $page * $per_page;
        $list->setRecordCount(count($copy_list));
        $i = 0;
        $tmp = array();
        foreach ($copy_list as $row) {
            $i++;
            if ($i > $from && $i <= $to) {
                $tmp[] = $row;
            }
        }
        if ($this->moduleConfig->gitlab && $this->moduleConfig->gitlab->host) {
            $list->addButtonCustom("<button class=\"button\" onclick=\"load('index.php?module=admin&action=modules&add_mod=-1&tab=available')\">Загрузить с GitLab</button>");
        }

        $list->data 		= $tmp;
        $list->addURL 		= $this->app . "&tab=available&add_mod=0";
        $list->editURL 		= $this->app . "&tab=available&add_mod=TCOL_00";
        $list->deleteKey	= "core_available_modules.id";
        $list->showTable();
    }

    /**
     * Список модулей из внешнего репозитория
     * @throws \Zend_Db_Adapter_Exception
     */
    public function getRepoModules() {
        //параметр со списком репозиториев
        $s_id = $this->db->fetchOne("
                    SELECT id
                    FROM core_settings
                    WHERE `code` = 'repo'
                    LIMIT 1
                ");
        if (empty($s_id)) {
            $this->db->insert('core_settings', array(
                'code'           => 'repo',
                'type'           => 'text',
                'system_name'    => $this->_('Адреса репозиториев для загрузки модулей'),
                'value'    		 => '',
                'visible'        => 'Y',
                'is_custom_sw'   => 'Y',
                'is_personal_sw' => 'N'
            ));
            $s_id = $this->db->lastInsertId("core_settings");
        }
        //достаем список репозиторием
        $mod_repos = $this->getSetting('repo');
        if (empty($mod_repos)) {

            echo
                "<br>
                 <div class=\"text-warning\">
                    Устоновка модулей из репозитория недоступна<br>
                    <span>
                        Создайте дополнительный параметр 'repo', содержащий репозиториев, разделенных ';'  (адреса вида https://REPOSITORY.COM/api/webservice?reg_apikey=YOUR_KEY)
                        <br>
                        <a href=\"#module=admin&action=settings&edit={$s_id}\">" . $this->_("Указать адреса репозиториев") . "</a>
                    </span>
                </div>";

        } else {
            echo
            "<br>
                 <div class=\"text-primary\">
                    Репозитории<br>
                    <span>
                        Для работы с репозиториями используется параметр \"repo\", содержащий адреса репозиториев (с регистрацией в репозитории https://REPOSITORY.COM/api/webservice?reg_apikey=REG_APIKEY, без регистрации https://REPOSITORY.COM/api/repo?apikey=APIKEY). Адреса разделяются \";\".
                        <br>
                        <a href=\"#module=admin&action=settings&edit={$s_id}\">Указать адреса репозиториев</a>
                    </span>
                </div>";
        }
        $mod_repos = explode(";", $mod_repos);

        //готовим аякс запросы к репозиториям
        foreach($mod_repos as $i => $repo){
            $repo = trim($repo);
            if (!empty($repo)){

                echo "<h3>Доступные модули из репозитория - " . $repo . ":</h3>";

                echo "<div id=\"repo_{$i}\"> Подключаемся...</div>";
                echo "<script type=\"text\/javascript\" language=\"javascript\">";
                echo    "$(document).ready(function () {";//ассинхронно получаем списки из репозитория
                echo        "window.setTimeout(modules.repo({$i}), 1);";
                echo    "});";
                echo "</script>";
                echo "<br><br><br>";
            }
        }
    }

    /**
     * Список установленных модулей
     * @throws \Exception
     */
    public function getListInstalled() {
        $list = new \listTable('mod');
        $list->table = "core_modules";
        $list->SQL = "SELECT m_id,
                             m_name,
                             module_id,
                             version,
                             is_system,
                             is_public,
                             '' AS act,
                             visible
                        FROM core_modules
                        WHERE m_id > 0
                       ORDER BY seq";
        $list->addColumn($this->_("Модуль"),        "250", "HTML");
        $list->addColumn($this->_("Идентификатор"), "150", "TEXT");
        $list->addColumn($this->_("Версия"),        "",    "TEXT");
        $list->addColumn($this->_("Системный"),     "100", "TEXT");
        $list->addColumn($this->_("Отображаемый"),  "120", "TEXT");
        $list->addColumn($this->_("Действие"),      "70",  "BLOCK", "", "", false);
        $list->addColumn("",                        "1",   "STATUS_INLINE", "core_modules.visible");

        $list->getData();
        $mods = [];
        foreach ($list->data as $key => $row) {
            // Системный
            $list->data[$key][4] = $row[4] == 'Y'
                ? '<span class="text-info">да</span>'
                : '<span class="text-success">нет</span>';

            // Отображаемый
            $list->data[$key][5] = $row[5] == 'Y'
                ? '<span class="text-info">да</span>'
                : '<span class="text-warning">нет</span>';

            // Действие
            $mods[$row[2]] = $row[0];
            $list->data[$key][6] = "
                <div style=\"display: inline-block;\" onclick=\"modules.uninstallModule('" . strip_tags($row[1]) . "', '".$row[3]."', '".$row[0]."');\"><img src=\"core2/html/".THEME."/img/box_uninstall.png\" border=\"0\" title=\"Разинсталировать\" /></div>
                <div style=\"display: inline-block;\" onclick=\"modules.refreshFiles('" . strip_tags($row[1]) . "', '".$row[3]."', '".$row[2]."');\"><img src=\"core2/html/".THEME."/img/page_refresh.png\" border=\"0\" title=\"Перезаписать файлы\" /></div>
            ";
        }
        $list->paintCondition	= "'TCOL_06' == 'N'";
        $list->paintColor		= "ffffee";

        $list->addURL 			= $this->app . "&edit=0";
        $list->editURL 			= $this->app . "&edit=TCOL_00";
        $list->noCheckboxes = "yes";

        $list->showTable();

        //проверка после загрузки страницы наличия обновлений
        $mods = json_encode($mods);
        $theme = THEME;
        $script = "<script type=\"text\/javascript\">
                        $(document).ready(function(){
                            //ассинхронно выполняем поиск обновлений
                            window.setTimeout(modules.checkModsUpdates({$mods}, '{$theme}'), 1);
                        });
                    </script>";

        echo $script;
    }


    /**
     * Установленные модули
     * @param int $refid
     * @return string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function getEditInstalled(int $refid = 0): string {

        $edit         = new \editTable('mod');
        $selected_dep = [];
        $field        = '';
        $mod_list     = $this->db->fetchAll("
            SELECT module_id,
                   m_name
            FROM core_modules
            WHERE m_id != ?
        ", $refid);


        $dep_list = [];

        if ($refid > 0) {
            $module           = $this->dataModules->find($refid)->current();
            $mod_dependencies = $module->dependencies;
            $dep              = [];

            if ($mod_dependencies) {
                $dep = base64_decode($mod_dependencies);
                $dep = @unserialize($dep);

                if (is_array($dep)) {
                    $selected_dep = [];
                    foreach ($dep as $variable) {
                        $selected_dep[] = $variable['module_id'];
                    }
                } else {
                    $dep = [];
                }
            }

            $availableModules = [];
            foreach ($mod_list as $variable) {
                $availableModules[] = $variable['module_id'];
            }


            $dep      = array_merge($dep, $mod_list);
            foreach ($dep as $variable) {
                // FIXME иногда переменной m_name нету у тех модулей которые имеют зависимости
                $m_name = isset($variable['m_name']) ? $variable['m_name'] : (isset($variable['module_name']) ? $variable['module_name'] : '');
                //FIXME $variable['module_name'] не всегда существует
                $edit->addParams("dep_" . $variable['module_id'], htmlspecialchars($m_name));
                if (!in_array($variable['module_id'], $availableModules)) {
                    $variable['m_name'] .= " <i style=\"color:#F44336\">(deleted)</i>";
                }
                $dep_list[$variable['module_id']] = $m_name;
            }


            $selected_dep = implode(",", $selected_dep);
            $field = "'$selected_dep' AS ";

        } else {
            foreach ($mod_list as $variable) {
                $edit->addParams("dep_" . $variable['module_id'], htmlspecialchars($variable['m_name']));
                $dep_list[$variable['module_id']] = $variable['m_name'];
            }
        }

        $access_default = [];
        $custom_access  = '';

        if ($refid > 0) {

            $access_default = unserialize(base64_decode($module->access_default ?? ''));
            $access_add 	= unserialize(base64_decode($module->access_add ?? ''));
            if (is_array($access_add) && count($access_add)) {
                foreach ($access_add as $key => $value) {
                    $id = uniqid('', true);
                    $custom_access .= '<input type="text" class="input" name="addRules[' . $id . ']" value="' . $key . '"/>'.
                        '<input type="checkbox" onchange="checkToAll(this)" id="access_' . $id . '_all" name="value_all[' . $id . ']" value="all" ' . ($value == 'all' ? 'checked="checked"' : '') . '/><label>Все</label>'.
                        '<input type="checkbox" name="value_owner[' . $id . ']" id="access_' . $id . '_owner" value="owner" ' . (($value == 'all' || $value == 'owner') ? ' checked="checked"' : '') . ($value == 'all' ? ' disabled="disabled"' : '') . '/><label>Владелец</label><br>';
                }
            }

        }
        $checked = 'checked="checked"';
        $disabled = 'disabled="disabled"';

        $tpl = new \Templater2();
        $tpl->loadTemplate('core2/mod/admin/assets/html/access_default.html');
        $tpl->assign([
            '{preff}' => '',

            '{access}' => (! empty($access_default['access']) ? $checked : ''),

            '{list_all}'            => (! empty($access_default['list_all']) ? $checked : ''),
            '{list_all_list_owner}' => (! empty($access_default['list_all']) || ! empty($access_default['list_owner']) ? $checked : ''),
            '{list_all_disabled}'   => (! empty($access_default['list_all']) ? $disabled : ''),

            '{read_all}'            => (! empty($access_default['read_all']) ? $checked : ''),
            '{read_all_read_owner}' => (! empty($access_default['read_all']) || ! empty($access_default['read_owner']) ? $checked : ''),
            '{read_all_disabled}'   => (! empty($access_default['read_all']) ? $disabled : ''),

            '{edit_all}'            => (! empty($access_default['edit_all']) ? $checked : ''),
            '{edit_all_edit_owner}' => (! empty($access_default['edit_all']) || ! empty($access_default['edit_owner']) ? $checked : ''),
            '{edit_all_disabled}'   => (! empty($access_default['edit_all']) ? $disabled : ''),

            '{delete_all}'              => (! empty($access_default['delete_all']) ? $checked : ''),
            '{delete_all_delete_owner}' => (! empty($access_default['delete_all']) || ! empty($access_default['delete_owner']) ? $checked : ''),
            '{delete_all_disabled}'     => (! empty($access_default['delete_all']) ? $disabled : ''),
        ]);
        $access = $tpl->parse();

        $rules  = "<div id=\"xxx\">{$custom_access}</div>";
        $rules .= '<div><span id="new_attr" class="newRulesModule" onclick="modules.newRule(\'xxx\')">Новое правило</span></div>';

        $switch = ['Y' => 'да', 'N' => 'нет'];


        $edit->SQL = $this->db->quoteInto("
            SELECT m_id,
                   m_name,
                   module_id,
                   is_system,
                   is_public,
                   isset_home_page,
                   $field dependencies,
                   access_default,
                   access_add								   
            FROM core_modules
            WHERE m_id = ?
        ", $refid);

        $edit->addControl("Модуль:", "TEXT", "maxlength=\"60\" size=\"60\"", "", "", true);
        if ($refid > 0) {
            $edit->addControl($this->_("Идентификатор:"), "PROTECTED");
        } else {
            $edit->addControl($this->_("Идентификатор:"), "TEXT", "maxlength=\"20\"", " " . $this->_("маленикие латинские буквы или цифры"), "", true);
        }
        $edit->addControl($this->_("Системный:"),                      "RADIO", "", "", "N"); $edit->selectSQL[] = $switch;
        $edit->addControl($this->_("Отображаемый:"),                   "RADIO", "", "", "N"); $edit->selectSQL[] = $switch;
        $edit->addControl($this->_("Есть главная страница:"),          "RADIO", "", "", "Y"); $edit->selectSQL[] = $switch;
        $edit->addControl($this->_("Зависит от модулей:"),             "MULTISELECT2", "style=\"width:317px\"", "", $selected_dep); $edit->selectSQL[] = $dep_list;
        $edit->addControl($this->_("Доступ по умолчанию:"),            "CUSTOM", $access);
        $edit->addControl($this->_("Дополнительные правила доступа:"), "CUSTOM", $rules);

        $edit->addButtonSwitch('visible', 	$this->db->fetchOne("SELECT 1 FROM core_modules WHERE visible = 'Y' AND m_id=? LIMIT 1", $refid));

        /*if ($is_visible) {
            if (count($list_name_modules) > 0) {
                $get_param = base64_encode(serialize($list_id_modules));
                $str = implode(',', $list_name_modules);
                $edit->addButtonCustom("<button type=\"button\" onclick=\"if(confirm('Для деактивации модуля необходимо отключить следующие модули: ".$str." .Выполнить отключение модулей?')) {document.location='?module=admin&action=modules&loc=core&edit=".$_GET['edit']."&module_off=".$get_param."'} else {return false}\"><img id=\"switch_on\" src=\"core/img/on.png\"/></button>");
            } else {
                $edit->addButtonCustom("<button type=\"button\" onclick=\"if(confirm('Деактивировать модуль?')) {document.location='?module=admin&action=modules&loc=core&module_off=1&edit=".$_GET['edit']."'} else {return false}\"><img id=\"switch_on\" src=\"core/img/on.png\"/></button>");
            }
        } else {
            $edit->addButtonCustom("<button type=\"button\" onclick=\"if(confirm('Активировать модуль?')) {document.location='?module=admin&action=modules&loc=core&module_on=1&edit=".$_GET['edit']."'} else {return false}\"><img id=\"switch_on\" src=\"core/img/off.png\"/></button>");
        }*/


        $edit->back = $this->app;
        $edit->save("xajax_saveModule(xajax.getFormValues(this.id))");

        return $edit->render();
    }


    /**
     * @param int $module_id
     * @param int $submodule_id
     * @return string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function getEditSubmodule(int $module_id, int $submodule_id = 0): string {

        $edit = new \editTable('submod');
        $edit->SQL = $this->db->quoteInto("
            SELECT  sm_id,
                    sm_name,
                    sm_key,
                    sm_path,
                    seq,
                    access_default,
                    access_add
            FROM core_submodules
            WHERE m_id = ?
             AND sm_id = ?
        ", $module_id, null, 1);
        $edit->SQL = $this->db->quoteInto($edit->SQL, $submodule_id, null, 1);



        $access_default 	= [];
        $custom_access 		= '';
        if ($submodule_id) {
            $res = $this->dataSubModules->find($submodule_id)->current();
            $access_default = $res->access_default ? unserialize(base64_decode($res->access_default)) : [];
            $access_add 	= $res->access_add ? unserialize(base64_decode($res->access_add)) : [];
            if (is_array($access_add) && count($access_add)) {
                foreach ($access_add as $key => $value) {
                    $id = uniqid();
                    $custom_access .= '<input type="text" class="input" name="addRules[' . $id . ']" value="' . $key . '"/>'.
                        '<label><input type="checkbox" onchange="checkToAll(this)" id="access_' . $id . '_all" name="value_all[' . $id . ']" value="all" ' . ($value == 'all' ? 'checked="checked"' : '') . '/> Все</label>'.
                        '<label><input type="checkbox" name="value_owner[' . $id . ']" id="access_' . $id . '_owner" value="owner" ' . (($value == 'all' || $value == 'owner') ? ' checked="checked"' : '') . ($value == 'all' ? ' disabled="disabled"' : '') . '/> Владелец</label><br>';
                }
            }
        }


        $checked  = 'checked="checked"';
        $disabled = 'disabled="disabled"';

        $tpl = new \Templater2();
        $tpl->loadTemplate('core2/mod/admin/assets/html/access_default.html');
        $tpl->assign([
            '{preff}' => 'sub',

            '{access}' => (! empty($access_default['access']) ? $checked : ''),

            '{list_all}'            => (! empty($access_default['list_all']) ? $checked : ''),
            '{list_all_list_owner}' => (! empty($access_default['list_all']) || ! empty($access_default['list_owner']) ? $checked : ''),
            '{list_all_disabled}'   => (! empty($access_default['list_all']) ? $disabled : ''),

            '{read_all}'            => (! empty($access_default['read_all']) ? $checked : ''),
            '{read_all_read_owner}' => (! empty($access_default['read_all']) || ! empty($access_default['read_owner']) ?: ''),
            '{read_all_disabled}'   => (! empty($access_default['read_all']) ? $disabled : ''),

            '{edit_all}'            => (! empty($access_default['edit_all']) ? $checked : ''),
            '{edit_all_edit_owner}' => (! empty($access_default['edit_all']) || ! empty($access_default['edit_owner']) ? $checked : ''),
            '{edit_all_disabled}'   => (! empty($access_default['edit_all']) ? $disabled : ''),

            '{delete_all}'              => (! empty($access_default['delete_all']) ? $checked : ''),
            '{delete_all_delete_owner}' => (! empty($access_default['delete_all']) || ! empty($access_default['delete_owner']) ? $checked : ''),
            '{delete_all_disabled}'     => (! empty($access_default['delete_all']) ? $disabled : ''),
        ]);

        $access = $tpl->parse();

        $seq = '1';
        if (empty($submodule_id)) {
            $seq = $this->db->fetchOne("
                SELECT MAX(seq) + 5 
                FROM core_submodules 
                WHERE m_id = ? 
                LIMIT 1
            ", $module_id);

            if ( ! $seq) {
                $seq = '1';
            }
        }

        $rules  = "<div id=\"xxxsub\">{$custom_access}</div>";
        $rules .= '<div><span id="new_attr" class="newRulesSubModule" onclick="modules.newRule(\'xxxsub\')">Новое правило</span></div>';




        $edit->addControl($this->_("Субмодуль:"), "TEXT", "maxlength=\"60\" size=\"60\"", "", "", true);

        if ($submodule_id > 0) {
            $edit->addControl($this->_("Идентификатор:"), "PROTECTED");
        } else {
            $edit->addControl($this->_("Идентификатор:"), "TEXT", "maxlength=\"20\"", $this->_(" маленикие латинские буквы или цифры"), "", true);
        }

        $edit->addControl($this->_("Адрес внешнего ресурса:"),         "TEXT");
        $edit->addControl($this->_("Позиция в меню:"),                 "NUMBER", "size=\"2\"", "", $seq, true);
        $edit->addControl($this->_("Доступ по умолчанию:"),            "CUSTOM", $access);
        $edit->addControl($this->_("Дополнительные правила доступа:"), "CUSTOM", $rules);

        $edit->addButtonSwitch('visible', $this->db->fetchOne("
            SELECT 1 
            FROM core_submodules 
            WHERE visible = 'Y' 
              AND sm_id = ? 
            LIMIT 1
        ", $submodule_id));

        if (empty($submodule_id)) {
            $edit->setSessFormField('m_id', $module_id);
        }

        $edit->back = "{$this->app}&edit={$module_id}&tab=submodules";
        $edit->addButton($this->_("Отменить"), "load('{$this->app}&edit={$module_id}&tab=submodules')");
        $edit->save("xajax_saveModuleSub(xajax.getFormValues(this.id))");

        return $edit->render();
    }


    /**
     * Список субмодулей
     * @param int $module_id
     * @return string
     */
    public function getListSubmodules(int $module_id): string {

        $list = new \listTable('submod');

        $list->SQL = $this->db->quoteInto("
            SELECT sm_id,
                  sm_name,
                  sm_key,
                  sm_path,
                  seq,
                  visible
            FROM core_submodules
            WHERE m_id = ?
            ORDER BY seq, sm_name
        ", $module_id);

        $list->addColumn($this->_("Субмодуль"),     "",    "TEXT");
        $list->addColumn($this->_("Идентификатор"), "150", "TEXT");
        $list->addColumn($this->_("Путь"),          "300", "TEXT");
        $list->addColumn($this->_("Позиция"),       "80",  "TEXT");
        $list->addColumn("",                        "1%", "STATUS_INLINE", "core_submodules.visible");

        $list->paintCondition	= "'TCOL_05' == 'N'";
        $list->paintColor		= "ffffee";

        $list->addURL    = $this->app . "&edit={$module_id}&tab=submodules&editsub=0";
        $list->editURL   = $this->app . "&edit={$module_id}&tab=submodules&editsub=TCOL_00";
        $list->deleteKey = "core_submodules.sm_id";

        return $list->render();
    }


    /**
     * Gitlab репозитории
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function getTableGitlab(): string {

        $table = new Table\Data("{$this->resId}xxx_gitlab");
        $table->hideCheckboxes();
        $table->setAjax();

        $modules = $this->getGitlabModules();
        $table->setData($modules);

        $table->addSearch($this->_("Модуль"), 'path');
        $table->addSearch($this->_("Версия"), 'version');
        $table->addSearch($this->_("Автор"),  'author');
        $table->addSearch($this->_("Дата"),   'date', $table::SEARCH_DATE);


        $table->addColumn($this->_("Модуль"), 'path',    $table::COLUMN_HTML);
        $table->addColumn($this->_('Версия'), 'version', $table::COLUMN_TEXT, 100);
        $table->addColumn($this->_("Автор"),  'author',  $table::COLUMN_TEXT, 250);
        $table->addColumn($this->_("Дата"),   'date',    $table::COLUMN_DATETIME, 120);


        $data = $table->fetchRows();
        if ( ! empty($data)) {
            foreach ($data as $row) {

                $row->module = $row->path->getValue();

                $path       = $row->path->getValue();
                $path_end   = mb_substr($path, mb_strrpos($path, '/') + 1);
                $path_begin = mb_substr($path, 0, mb_strrpos($path, '/') + 1);
                $row->path  = "<span class=\"text-muted\">{$path_begin}</span><b>{$path_end}</b>";
            }
        }

        $update_table = $this->_('Обновить список');

        $table->setEditUrl("javascript:edit.modal2.choose('TCOL_MODULE|TCOL_VERSION', 'TCOL_MODULE TCOL_VERSION');");
        $table->addCustomControl("<button class=\"button btn btn-sm btn-default\" onclick=\"modules.updateTable('" . THEME . "')\">{$update_table}</button>");

        return $table->render();
    }


    /**
     * Очистка списка модулей из Gitlab
     * @return bool
     */
    public function gitlabClean(): bool {

        $gitlab_cache_key = 'gitlab_modules';

        if ($this->cache->test($gitlab_cache_key)) {
            $this->cache->clean($gitlab_cache_key);
        }

        return true;
    }


    /**
     * Получение списка модулей из Gitlab
     * @return array|bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getGitlabModules(): array {

        $gitlab_cache_key = 'gitlab_modules';

        if ($this->cache->test($gitlab_cache_key)) {
            $modules = $this->cache->load($gitlab_cache_key);

        } else {
            $gitlab         = new Gitlab();
            $gitlab_modules = $gitlab->getTags();
            $modules        = [];

            if ( ! empty($gitlab_modules)) {
                foreach ($gitlab_modules as $module_id => $module) {

                    if ( ! empty($module['tags'])) {
                        foreach ($module['tags'] as $tag) {
                            $modules[] = [
                                'module_id' => $module_id,
                                'path'      => $module['name'],
                                'version'   => $tag['name'],
                                'author'    => "{$tag['author_name']} ({$tag['author_email']})",
                                'date'      => $tag['authored_date'],
                            ];
                        }
                    }
                }
            }

            if ( ! empty($modules)) {
                $modules = \Tool::arrayMultisort($modules, 'date', SORT_DESC);
            }

            $this->cache->save($modules, $gitlab_cache_key);
        }

        return $modules ?: [];
    }
}