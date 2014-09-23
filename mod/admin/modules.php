<?
require_once 'core2/mod/admin/install.php';


//скачивание архива модуля
if (!empty($_GET['download_mod'])) {
    $install = new InstallModule();
    $install->download_zip($_GET['download_mod']);
}


/* скачивание архива шаблона */
if (!empty($_GET['download_mod_tpl'])) {
    try {
        $zip_file = $this->config->temp.'/' . $_GET['download_mod_tpl'] . '_tmp_'. session_id() . ".zip";

        if (file_exists($zip_file)){
            unlink($zip_file);
        }

        $template_path = "core2/mod_tpl/" . $_GET['download_mod_tpl'];

        $zip = new ZipArchive;
        $res = $zip->open($zip_file, ZipArchive::CREATE);
        if ($res === TRUE) {
            $dir = opendir($template_path) or die("Не могу открыть");
            while ($file = readdir($dir)){
                if ($file != "." && $file != "..") {
                    if (is_dir($template_path."/".$file)) {//если есть вложеные папки

                        $dir2 = opendir($template_path."/".$file) or die("Не могу открыть");
                        while ($file2 = readdir($dir2)){
                            if ($file2 != "." && $file2 != "..") {
                                if (is_file($template_path . "/" . $file . "/" . $file2))  {
                                    $zip->addFile($template_path . "/" . $file . "/" . $file2, $file . "/" . $file2);
                                }
                            }
                        }

                    } else if (is_file($template_path."/".$file))  {
                        $zip->addFile($template_path."/".$file, $file);
                    }
                }
            }
            $zip->close();
        } else {
            throw new Exception("Ошибка создания архива");
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . $_GET['download_mod_tpl'] . ".zip");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($zip_file);
        exit;


    }
    catch (Exception $e) {
        echo $e->getMessage();
    }

}




$this->printJs("core2/mod/admin/mod.js");

$tab = new tabs('mod'); 
$tab->addTab("Модули", 			$app, 130);
$tab->addTab("Доступные модули",	$app, 130);
$tab->addTab("Шаблоны модулей",	$app, 130);
$tab->beginContainer("Модули");

$sid = session_id();
	if ($tab->activeTab == 1) {		
		$errorNamespace = new Zend_Session_Namespace('Error');
		
		if (isset($_POST['uninstall'])) {
			/* Деинсталяция модуля.Провераем uninstall.sql */
			/* Если не найден, останавливаем деинсталяцию*/

            echo "<h3>Деинсталяция модуля</h3>";
            $install = new InstallModule();

			try {

				$mId = $this->dataModules->find($_POST['uninstall'])->current()->module_id;

                if (!empty($mId)) {//если модуль существует

                    $install->moduleId = $mId;
                    $mod_inf = $this->db->fetchRow("SELECT * FROM `core_modules` WHERE `module_id`='".$mId."'");
                    $modulePath 	= (strtolower($mod_inf['is_system']) == "y" ? "core2/" : "") .  "mod/" . $mId;

                    if ($install->is_used_by_other_modules($mId) === false) {//если не используется другими модулями

                        /* Удаляем таблицы модуля*/
                        if (!empty($mod_inf['uninstall'])) {
                            $sql = str_replace('#__', 'mod_' . $mId, $mod_inf['uninstall']);
                            if ($install->checkSQL($sql)) {
                                $this->db->query($sql);
                                $install->add_notice("Таблицы модуля", "Удаление таблиц", "Успешно", "mod_info");
                            } else {
                                $install->add_notice("Таблицы модуля", "Таблицы не удалены", "Попытка удаления таблиц не относящихся к модулю!", "mod_warning");
                            }
                        } else {
                            $install->add_notice("Таблицы модуля", "Таблицы не удалены", "Инструкции по удалению не найдены, удалите их самостоятельно!", "mod_warning");
                        }
                        //удаляем субмодули
                        $this->db->query("DELETE FROM `core_submodules` WHERE `m_id`='" . $mod_inf['m_id'] . "'");
                        $install->add_notice("Субмодули", "Удаление субмодулей", "Успешно", "mod_info");
                        //удаляем регистрацию модуля
                        $this->db->delete('core_modules', "module_id='$mId'");//удаляем из БД
                        $install->add_notice("Регистрация модуля", "Удаление сведений о модуле", "Выполнено", "mod_info");

                        if ($mod_inf['is_system'] == 'N') {
                            $install->Uninstall($modulePath);
                        } else {
                            $install->add_notice("Файлы модуля", "Файлы не удалены", "Файлы системных модулей удаляются вручную!", "mod_warning");
                        }

                    } else {//если используется другими модулями
                        throw new Exception("Модуль используется модулем '{$install->is_used_by_other_modules($mId)}'");
                    }

                    $install->add_notice("Деинсталяция", "Статус", "Завершена", "mod_info");
                    echo $install->print_notice($tab->activeTab);

                } else{//если модуль не существует
                    throw new Exception("Модуль уже удален или не существует!");
                }

			} catch (Exception $e) {
				$error = $e->getMessage();
                $install->add_notice("Деинсталяция", "Операция прервана, произведен откат транзакции", "Ошибка: {$error}", "mod_danger");
                echo $install->print_notice($tab->activeTab);
			}
            die;
		}

		if (isset($_GET['edit']) && $_GET['edit'] != '') {	
			$edit = new editTable('mod'); 
			$selected_dep = array();
			$dep_list = "SELECT module_id, m_name FROM core_modules WHERE m_id != '" . $_GET['edit'] . "'";
			$field = '';
			if ($_GET['edit'] > 0) {
				
				$SQL  = "SELECT dependencies
							 FROM core_modules
							 WHERE m_id = '" . $_GET['edit'] . "'";
				$res = $this->dataModules->find($_GET['edit'])->current()->dependencies;
				$dep = array();
				if ($res) {
					$dep = base64_decode($res);
					$dep = @unserialize($dep);
					
					if (is_array($dep)) {
						$selected_dep = array();
						foreach ($dep as $variable) {
							$selected_dep[] = $variable['module_id'];
						}
					} else {
						$dep = array();
					}
				}
				
				$res = $this->db->fetchAll($dep_list);
				$availableModules = array();
				foreach ($res as $variable) {
					$availableModules[] = $variable['module_id'];
				}
				
				$dep_list = array();
				$dep = array_merge($dep, $res);
				foreach ($dep as $variable) {
					$edit->addParams("dep_" . $variable['module_id'], $variable['m_name']);
					if (!in_array($variable['module_id'], $availableModules)) {
						$variable['m_name'] .= " <font color=\"red\">(deleted)</font>";
					}
					$dep_list[$variable['module_id']] = $variable['m_name'];
				}
				
				
				$selected_dep = implode(",", $selected_dep);
				$field = "'$selected_dep' AS ";
				
				
			}
			if (isset($_GET['module_on'])) {
				$list_dep = $this->dataModules->find($_GET['edit'])->current()->dependencies;
				$modules = array();
				if ($list_dep) {
					$dep = unserialize(base64_decode($list_dep));
					$error = "Для активации модуля необходимо включить модули:";										
					if (is_array($dep)) {						
						foreach ($dep as $val) {
							$is_on =  $this->db->fetchOne("SELECT 1 FROM core_modules WHERE visible = 'Y' AND module_id=? LIMIT 1", $val['module_id']);
							if (!$is_on) {																								
								if (!isset($val['m_name'])) {									
									$modules[] = $this->db->fetchOne("SELECT m_name FROM core_modules WHERE module_id=?", $val['module_id']);
								} else {
									$modules[] = $val['m_name'];
								}
							}
							
						}						  
					}
				}
				if (count($modules) > 0) {
					$edit->error = "Для активации модуля необходимо включить модули:".implode(",", $modules);
				} else {
					$where = $this->db->quoteInto('m_id = ?', $_GET['edit']);
					$this->db->update("core_modules", array("visible" => "Y"), $where);
				}																
			} 
			
			if (isset($_GET['module_off'])) {
				$modules_off = unserialize((base64_decode($_GET['module_off'])));
				if (is_array($modules_off)) {
					foreach ($modules_off as $value_off) {
						$where  = $this->db->quoteInto('module_id = ?', $value_off);
						$this->db->update("core_modules", array("visible" => "N"), $where);						
					}					
				} 	
				$where2 = $this->db->quoteInto('m_id = ?', $_GET['edit']);
				$this->db->update("core_modules", array("visible" => "N"), $where2);			
				
			}
			
			$array_dep = $this->db->fetchAll("SELECT module_id,m_name,dependencies FROM core_modules WHERE visible='Y'");
			$module = $this->dataModules->find($_GET['edit'])->current();
			$id_module = $module->module_id;
			$list_id_modules = array();
			$list_name_modules = array();
			foreach ($array_dep as $value) {
				if ($value['dependencies']) {
					$dep_arr = unserialize(base64_decode($value['dependencies']));
					if (count($dep_arr) > 0) {
						foreach ($dep_arr as $module_val) {
							if ($module_val['module_id'] == $id_module) {
								$list_name_modules[] = $value['m_name'];
								$list_id_modules[] = $value['module_id'];
							}
						}						
					}					
				}				 
			}
			 
			$edit->SQL  = "SELECT  m_id,
								   m_name,
								   module_id,
								   is_system,
								   is_public,
								   $field dependencies,
								   seq,
								   access_default,
								   access_add								   
							  FROM core_modules
							 WHERE m_id = '" . $_GET['edit'] . "'";							 
			$edit->addControl("Модуль:", "TEXT", "maxlength=\"60\" size=\"60\"", "", "", true);
			if ($_GET['edit'] > 0) {
				$edit->addControl("Идентификатор:", "PROTECTED");
			} else {
				$edit->addControl("Идентификатор:", "TEXT", "maxlength=\"20\"", " маленикие латинские буквы или цифры", "", true);
			}
			$edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет'); 
			$edit->addControl("Системный:", "RADIO", "", "", "N");
			$edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет'); 
			$edit->addControl("Отображаемый:", "RADIO", "", "", "N");
			$edit->selectSQL[] = $dep_list; 			
			$edit->addControl("Зависит от модулей:", "CHECKBOX", "", "", $selected_dep);
			$seq = '';
			if ($_GET['edit'] == 0) {
				$seq = $this->db->fetchOne("SELECT MAX(seq) + 5 FROM core_modules LIMIT 1");
			}
			$edit->addControl("Позиция в меню:", "NUMBER", "size=\"2\"", "", $seq);
			$access_default 	= array();
			$custom_access 		= '';			
			if ($_GET['edit']) {
				$access_default = unserialize(base64_decode($module->access_default));
				$access_add 	= unserialize(base64_decode($module->access_add));
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
			
			$tpl = new Templater();
			$tpl->loadTemplate('core2/mod/admin/html/access_default.tpl');
			$tpl->assign(array(
				'{preff}' => '',
			
				'{access}' => (!empty($access_default['access']) ? $checked : ''),
				
				'{list_all}' => (!empty($access_default['list_all']) ? $checked : ''),
				'{list_all_list_owner}' => (!empty($access_default['list_all']) || !empty($access_default['list_owner']) ? $checked : ''),
				'{list_all_disabled}' => (!empty($access_default['list_all']) ? $disabled : ''),
				
				'{read_all}' => (!empty($access_default['read_all']) ? $checked : ''),
				'{read_all_read_owner}' => (!empty($access_default['read_all']) || !empty($access_default['read_owner']) ?$checked : ''),
				'{read_all_disabled}' => (!empty($access_default['read_all']) ? $disabled : ''),

				'{edit_all}' => (!empty($access_default['edit_all']) ? $checked : ''),
				'{edit_all_edit_owner}' => (!empty($access_default['edit_all']) || !empty($access_default['edit_owner']) ?$checked : ''),
				'{edit_all_disabled}' => (!empty($access_default['edit_all']) ? $disabled : ''),
			
				'{delete_all}' => (!empty($access_default['delete_all']) ? $checked : ''),
				'{delete_all_delete_owner}' => (!empty($access_default['delete_all']) || !empty($access_default['delete_owner']) ?$checked : ''),
				'{delete_all_disabled}' => (!empty($access_default['delete_all']) ? $disabled : ''),
			));			
			$access = $tpl->parse();
			$edit->addControl("Доступ по умолчанию:", "CUSTOM", $access);
			
			//CUSTOM ACCESS
			$is_visible = $this->db->fetchOne("SELECT 1 FROM core_modules WHERE visible = 'Y' AND m_id=? LIMIT 1", $_GET['edit']);			
			$rules = '<div id="xxx">' . $custom_access . '</div>';
			$rules .= '<div><span id="new_attr" class="newRulesModule" onclick="newRule(\'xxx\')">Новое правило</span></div>';
			$edit->addControl("Дополнительные правила доступа:", "CUSTOM", $rules);
			$edit->addButtonSwitch('visible', $this->db->fetchOne("SELECT 1 FROM core_modules WHERE visible = 'Y' AND m_id=? LIMIT 1", $_GET['edit']));
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
			
			
			$edit->back = $app;
			$edit->addButton("Вернуться к списку Модулей", "load('$app')");
			$edit->save("xajax_saveModule(xajax.getFormValues(this.id))");
			
			$edit->showTable();
			$tab = new tabs("submods");
			$tab->beginContainer('Субмодули');
			if (isset($_GET['editsub']) && $_GET['editsub'] != '') {
				$edit = new editTable('submod'); 
				$edit->SQL  = "SELECT  sm_id,
									   sm_name,
									   sm_key,
									   sm_path,
									   seq,
									   visible,
									   m_id,
									   access_default,
									   access_add
								  FROM core_submodules
								 WHERE m_id = '" . $_GET['edit'] . "'
								   AND sm_id = '" . $_GET['editsub'] . "'";
				$res = $this->db->fetchRow($edit->SQL);
				
				$edit->addControl("Субмодуль:", "TEXT", "maxlength=\"60\" size=\"60\"", "", "", true);
				
				if ($_GET['editsub'] > 0) {
					$edit->addControl("Идентификатор:", "PROTECTED");
				} else {
					$edit->addControl("Идентификатор:", "TEXT", "maxlength=\"20\"", " маленикие латинские буквы или цифры", "", true);
				}
				$edit->addControl("Адрес внешнего ресурса:", "TEXT");
				$seq = '1';
				if ($_GET['editsub'] == 0) {
					$seq = $this->db->fetchOne("SELECT MAX(seq) + 5 FROM core_submodules WHERE m_id = ? LIMIT 1", $_GET['edit']);
				}
				$edit->addControl("Позиция в меню:", "NUMBER", "size=\"2\"", "", $seq);
				$edit->selectSQL[] = array('Y' => 'вкл.', 'N' => 'выкл.'); 
				$edit->addControl("Статус:", "RADIO", "", "", "Y");
				$edit->addControl("", "HIDDEN", "", "", $_GET['edit']);
				
				$access_default 	= array();
				$custom_access 		= '';
				if ($_GET['editsub']) {
					$SQL = "SELECT access_default, access_add
							  FROM core_submodules
							 WHERE sm_id = ?";
					$res = $this->dataSubModules->find($_GET['editsub'])->current();
					$access_default = unserialize(base64_decode($res->access_default));
					$access_add 	= unserialize(base64_decode($res->access_add));
					if (is_array($access_add) && count($access_add)) {
						foreach ($access_add as $key => $value) {
							$id = uniqid();
							$custom_access .= '<input type="text" class="input" name="addRules[' . $id . ']" value="' . $key . '"/>'.
							'<input type="checkbox" onchange="checkToAll(this)" id="access_' . $id . '_all" name="value_all[' . $id . ']" value="all" ' . ($value == 'all' ? 'checked="checked"' : '') . '/><label>Все</label>'.
							'<input type="checkbox" name="value_owner[' . $id . ']" id="access_' . $id . '_owner" value="owner" ' . (($value == 'all' || $value == 'owner') ? ' checked="checked"' : '') . ($value == 'all' ? ' disabled="disabled"' : '') . '/><label>Владелец</label><br>';
						}
					}
				}
				$tpl = new Templater();
				$tpl->loadTemplate('core2/mod/admin/html/access_default.tpl');
				$tpl->assign(array(
					'{preff}' => 'sub',
					
					'{access}' => (!empty($access_default['access']) ? $checked : ''),
					
					'{list_all}' => (!empty($access_default['list_all']) ? $checked : ''),
					'{list_all_list_owner}' => (!empty($access_default['list_all']) || !empty($access_default['list_owner']) ? $checked : ''),
					'{list_all_disabled}' => (!empty($access_default['list_all']) ? $disabled : ''),
					
					'{read_all}' => (!empty($access_default['read_all']) ? $checked : ''),
					'{read_all_read_owner}' => (!empty($access_default['read_all']) || !empty($access_default['read_owner']) ?$checked : ''),
					'{read_all_disabled}' => (!empty($access_default['read_all']) ? $disabled : ''),
	
					'{edit_all}' => (!empty($access_default['edit_all']) ? $checked : ''),
					'{edit_all_edit_owner}' => (!empty($access_default['edit_all']) || !empty($access_default['edit_owner']) ?$checked : ''),
					'{edit_all_disabled}' => (!empty($access_default['edit_all']) ? $disabled : ''),
				
					'{delete_all}' => (!empty($access_default['delete_all']) ? $checked : ''),
					'{delete_all_delete_owner}' => (!empty($access_default['delete_all']) || !empty($access_default['delete_owner']) ?$checked : ''),
					'{delete_all_disabled}' => (!empty($access_default['delete_all']) ? $disabled : ''),
				));		
					
				$access = $tpl->parse();
				$edit->addControl("Доступ по умолчанию:", "CUSTOM", $access);
				
				$rules = '<div id="xxxsub">' . $custom_access . '</div>';
				$rules .= '<div><span id="new_attr" class="newRulesSubModule" onclick="newRule(\'xxxsub\')">Новое правило</span></div>';
				$edit->addControl("Дополнительные правила доступа:", "CUSTOM", $rules);
				
				$edit->back = $app . "&edit=" . $_GET['edit'];
				$edit->addButton("Отменить", "load('{$app}&edit={$_GET['edit']}')");
				$edit->save("xajax_saveModuleSub(xajax.getFormValues(this.id), {sm_name:'req',m_id:'req',visible:'req'})");
				
				$edit->showTable();
			}
			
			$list = new listTable('submod'); 
		
			$list->SQL = "SELECT sm_id,
								 sm_name,
								 sm_path,
								 seq,
								 sm.visible
							FROM core_submodules AS sm
							WHERE m_id = '" . $_GET['edit'] . "'
						   ORDER BY seq, sm_name";
			$list->addColumn("Субмодуль", "", "TEXT");
			$list->addColumn("Путь", "", "TEXT");
			$list->addColumn("Позиция", "", "TEXT");
			$list->addColumn("", "1%", "STATUS");
			
			$list->paintCondition	= "'TCOL_05' == 'N'";
			$list->paintColor		= "ffffee";
			
			$list->addURL 			= $app . "&edit={$_GET['edit']}&editsub=0";
			$list->editURL 			= $app . "&edit={$_GET['edit']}&editsub=TCOL_00";
			$list->deleteKey		= "core_submodules.sm_id";
			
			$list->showTable();
			$tab->endContainer();
		}
		else {

			$list = new listTable('mod');
		
			$list->SQL = "SELECT m_id,
								 m_name,
								 module_id,
								 version,
								 is_system,
								 is_public,
								 seq,	
								 '',							 								 
								 visible
							FROM core_modules
							WHERE m_id > 0
						   ORDER BY seq";
			$list->addColumn("Модуль", "", "TEXT");
			$list->addColumn("Идентификатор", "", "TEXT");
			$list->addColumn("Версия", "", "TEXT");
			$list->addColumn("Системный", "", "TEXT");
			$list->addColumn("Отображаемый", "", "TEXT");
			$list->addColumn("Позиция", "1%", "TEXT");
			$list->addColumn("Действие", "1%", "BLOCK", "align=\"center\"");
			$list->addColumn("", "1%", "STATUS_INLINE", "core_modules.visible");


			$data = $list->getData();
			foreach ($data as $key => $val) {
				$data[$key][7] = "<div onclick=\"uninstallModule('" . $val[1] . "', '".$val[3]."', '".$val[0]."');\"><img src=\"core2/html/".THEME."/img/box_uninstall.png\" border=\"0\" title=\"Разинсталировать\" /></div>";
				 
			}
			$list->data = $data;
			$list->paintCondition	= "'TCOL_07' == 'N'";
			$list->paintColor		= "ffffee";
			
			$list->addURL 			= $app . "&edit=0";
			$list->editURL 			= $app . "&edit=TCOL_00";			
			$list->noCheckboxes = "yes";
			
			$list->showTable();
		}
		
	}
	
	if ($tab->activeTab == 2) { //ДОСТУПНЫЕ МОДУЛИ

        $edit = new editTable('mod_available');
        $errorNamespace = new Zend_Session_Namespace('Error');
        $edit->error = $errorNamespace->ERROR;

		if (isset($_GET['add_mod']) && $_GET['add_mod'] != '' && $_GET['add_mod'] != 0) {
				
            $edit = new editTable('modules_install');
            $edit->SQL = "SELECT 1";

            $res = $this->db->fetchRow("SELECT name, version, readme, install_info
                                        FROM core_available_modules WHERE id=?", $_GET['add_mod']);
            $title = "<h2><b>Инструкция по установке модуля</b></h2>";
            $content = $res['readme'];
            $inf = unserialize($res['install_info']);

            $modId = $inf['install']['module_id'];
            $modVers = $inf['install']['version'];
            $modName = $inf['install']['name'];
            $is_module = $this->db->fetchRow("SELECT m_id FROM core_modules
                                            WHERE module_id=? and version=?",array($modId, $modVers));

            if (empty($content)) {
                $content = $title . "<br>Информация по установке отсутствует";
            } else {
                $content = $title . $content;
            }

            echo $content;
            if (!is_array($is_module)) {
                $tpl = new Templater("core2/html/" . THEME . "/buttons.tpl");
                $tpl->touchBlock("install_button");
                $tpl->assign("modName", $modName);
                $tpl->assign("modVers", $modVers);
                $tpl->assign("modInstall", $_GET['add_mod']);
                $edit->addButtonCustom($tpl->parse());
                $edit->readOnly = true;
            }

            $edit->addButton("Вернуться к скиску модулей", "load('$app&tab_mod=2')");

            $edit->addButtonCustom('<input class="button" type="button" value="Скачать файлы модуля" onclick="loadPDF(\'index.php?module=admin&action=modules&loc=core&tab_mod=2&download_mod=' . $_GET['add_mod'] . '\')">');

            $edit->showTable();

            die;
        }


        /* Добавление нового модуля */
				
        if (isset($_GET['add_mod']) && $_GET['add_mod'] == 0) {



             $edit->SQL = "SELECT id,
                                   name
                            FROM core_available_modules
                            WHERE id = '".$_GET['add_mod']."'";
             $edit->addControl("Файл архива(.zip)", "XFILE", "", "", "");
             $edit->classText['SAVE'] = "Загрузить";
             $edit->back = $app . "&tab_mod=2";
             $edit->save("xajax_saveAvailModule(xajax.getFormValues(this.id))");
             $edit->showTable();


        }


        /* Инсталяция модуля */
        if (!empty($_POST['install'])) {
            $install = new InstallModule();
            $this->db->beginTransaction();
            try {
                if ($install->checkInstall($_POST['install'])) {
                    echo "<h3>Обновляем модуль</h3>";
                    $install->Upgrate();

                } else {
                    echo "<h3>Устанавливаем модуль</h3>";
                    $install->Install();
                }
                $this->db->commit();
                echo $install->print_notice($tab->activeTab);

            } catch (Exception $e) {
                $this->db->rollback();
                $error = $e->getMessage();
                $install->add_notice("Установщик", "Установка прервана, произведен откат транзакции", "Ошибка: {$error}", "mod_danger");
                echo $install->print_notice($tab->activeTab);
                //TODO удалять таблицы модуля которые успели проинсталится
                die;
            }
            die;
        }



        /* Инсталяция модуля из репозитория */
        if (!empty($_POST['install_from_repo'])) {

            $this->db->beginTransaction();
            $install = new InstallModule();

            try {

                //готовим ссылку для запроса модуля из репозитория
                $key = base64_encode(serialize(array(
                    "server"    => strtolower(str_replace(array('http://','index.php'), array('',''), $_SERVER['HTTP_REFERER'])),
                    "request"   => $_POST['install_from_repo']
                )));

                $repo = trim($_POST['repo']);
                $url = $repo . "&module=repo&key=" . $key;

                //делаем запрос
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $curl_out = curl_exec($curl);
                $curl_error = curl_errno($curl) > 0 ? curl_errno($curl) . ": ". curl_error($curl) : "";
                curl_close($curl);

                $out = json_decode($curl_out);

                //обрабатываем результат
                if($out->status != 'success'){//если ошибка репозитория
                    throw new Exception("CURL - {$curl_error} {$curl_out}");
                } else {//если есть отсвет
                    $data = base64_decode($out->data);
                    if (!empty($data) && empty($out->massage)){//если есть данные и пустые сообщения устанавливаем модуль
                        $install->moduleData['data'] = $data;
                        if ($install->check_install_for_repo()) {
                            echo "<h3>Обновляем модуль</h3>";
                            $install->Upgrate();

                        } else {
                            echo "<h3>Устанавливаем модуль</h3>";
                            $install->Install();
                        }
                        $this->db->commit();
                        echo $install->print_notice($tab->activeTab);
                    }else{//если есть сообщение значит что-то не так
                        throw new Exception($out->massage);
                    }
                }

            } catch (Exception $e) {
                $this->db->rollback();
                $error = $e->getMessage();
                $install->add_notice("Установщик", "Установка прервана, произведен откат транзакции", "Ошибка: {$error}", "mod_danger");
                echo $install->print_notice($tab->activeTab);
            }
            die;
        }


		$list = new listTable('mod_available');
		$our_available_modules = $this->db->fetchAll("
            SELECT id,
                 name,
                 descr,
                 version,
                 install_info
            FROM core_available_modules
        ");
        $list->SQL = "SELECT id,
							 name,
							 descr,
							 version,
							 install_info
						FROM core_available_modules";
        $list->addColumn("Имя модуля", "", "TEXT");
        $list->addColumn("Описание", "", "TEXT");
        $list->addColumn("Версия", "150px", "TEXT");
        $list->addColumn("Действие", "3%", "BLOCK", 'align=center');

        $copy_list = $list->getData();
        $tmp = array();
        $listAllModules = $this->db->fetchAll("SELECT module_id, version FROM core_modules");
        foreach ($copy_list as $key=>$val) {
            $mData = unserialize(htmlspecialchars_decode($val[4]));
            $mVersion = $val[3];
            $mId = $mData['install']['module_id'];
            $mName = $val[1];
            $copy_list[$key][4] = "<div onclick=\"installModule('$mName', 'v$mVersion', '{$copy_list[$key][0]}')\"><img src=\"core2/html/".THEME."/img/box_out.png\" border=\"0\" title=\"Установить\"/></div>";
            foreach ($listAllModules as $allval) {
                if ($mId == $allval['module_id']) {
                    if ($mVersion == $allval['version']) {
                        $copy_list[$key][4] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Уже установлен\" border=\"0\"/></a>";
                    }
                }
            }

            $tmp[$mId][$mVersion] = $copy_list[$key];
        }

        //смотрим есть-ли разные версии одного мода
        //если есть, показываем последнюю, осатльные в спойлер
        $copy_list = array();
        foreach ($tmp as $module_id=>$val) {
            ksort($val);
            $max_ver = (max(array_keys($val)));
            $copy_list[$module_id] = $val[$max_ver];
            unset($val[$max_ver]);
            if (!empty($val)) {
                $copy_list[$module_id][3] .= " <a href=\"\" onclick=\"$('.mod_available_{$module_id}').toggle(); return false;\">Предыдущие версии</a><br>";
                $copy_list[$module_id][3] .= "<table width=\"100%\" class=\"mod_available_{$module_id}\" style=\"display: none;\"><tbody>";
                foreach ($val as $version=>$val) {
                    $copy_list[$module_id][3] .= "<tr><td style=\"border: 0px; padding: 0px;\">{$version}</td><td style=\"border: 0px; text-align: right; padding: 0px;\">{$val[4]}</td></tr>";
                }
                $copy_list[$module_id][3] .= "</tbody></table>";
            }
        }

        $list->data 		= $copy_list;
        $list->addURL 		= $app . "&add_mod=0&tab_mod=2";
        //$list->editURL 		= $app . "&tab_mod=2&add_mod=TCOL_00";
        $list->deleteKey	= "core_available_modules.id";


        //проверяем заданы ли ссылки на репозитории
        $mod_repos = $this->getSetting('repo');
        if (empty($mod_repos)){
            $list->error .= "<div>Для устоновки модулей из репозитория создайте ключь 'repo' с адресами репозиториев через ';' !</div>";
        }
        $mod_repos = explode(";", $mod_repos);


        $list->showTable();


        //готовим аякс запросы к репозиториям
        foreach($mod_repos as $i => $repo){
            if (!empty($repo)){

                $repo = trim($repo);

                echo "<h3>Доступные модули из репозитория - " . $repo . ":</h3>";

                echo "<div id=\"repo_{$i}\"> Подключаемся...</div>";
                echo "<script type=\"text\/javascript\" language=\"javascript\">";
                echo    "$(document).ready(function () {";
                echo        "modules.repo('{$repo}', {$i});";
                echo    "});";
                echo "</script>";
            }
        }

	}
	
	if ($tab->activeTab == 3) {

		if (isset($_GET['file_mod']) && $_GET['file_mod'] != ""){
			$readme = "core2/mod_tpl/".$_GET['file_mod']."/Readme.txt";
			$file = "<h2><b>Краткое описание шаблона ".$_GET['file_mod']."</b></h2>";
			
			if (file_exists($readme))
				$handle = fopen ($readme, "r");
				echo $file;
				while (!feof ($handle)) {
    			$buffer = fgets($handle, 100);
    			echo $buffer."<br>";
                }
			
		}


		$list = new listTable('mod_tamplates');
        $list->extOrder = true;
		$list->SQL = "SELECT 1";
		$list->addColumn("Имя шаблона", "", "TEXT");
		$list->addColumn("Описание", "", "TEXT");
		$list->addColumn("Загрузить", "5%", "BLOCK",'align=center', false);
		$list->getData();
		$dir = opendir("core2/mod_tpl");
		$folder = array();
		$i = 0;
		while ($file = readdir($dir))
		{		
			$i++;				
			if ($file != "." && $file != ".." && !strpos($file, "svn"))
				if(is_dir("core2/mod_tpl/".$file))
				{					
					
					$folder[$i][] = $i;
					$folder[$i][] = $file;
					$folder[$i][] = "";					 
					$folder[$i][] = "<a href=\"?module=admin&action=modules&loc=core&tab_mod=3&download_mod_tpl=".$file."\"><img src=\"core2/html/".THEME."/img/templates_button.png\" border=\"0\"/></a>";
					
				}
		}
		
		closedir($dir);
		$list->data = $folder;
		$list->editURL = $app."&tab_mod=3&file_mod=TCOL_01";
		$list->showTable();
	}


$tab->endContainer();

