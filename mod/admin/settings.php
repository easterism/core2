<?

$tab = new tabs('settings'); 
$tab->addTab("Настройки системы", 				$app, 130);
$tab->addTab("Дополнительные параметры", 		$app, 180);
$tab->addTab("Персональные параметры", 		$app, 180);
//$tab->addTab("Языки",					$app, 130);
//$tab->addTab("Переводы",					$app, 130);
$tab->beginContainer("Конфигурация");
	if ($tab->activeTab == 1) {
		
		$edit = new editTable('set'); 
		
		$res = $this->db->query("SELECT * FROM core_settings WHERE visible='Y' AND is_custom_sw='N'");
		$sql = array();
		$caps = array();
		while ($row = $res->fetch()) {
    		$sql[$row['code']] = $row['value'];
    		$caps[$row['code']] = $row['system_name'];
		}
		$edit->SQL  = array(array_merge(array('id' => 1), $sql));
		foreach ($sql as $caption => $value) {
			$edit->addControl($caps[$caption] . ":", "EDIT");
		}
		if (!empty($_REQUEST['edit']) && $_REQUEST['edit'] == 'yes') {
			$edit->addButton("Отменить", "load('$app')");
			$edit->back = $app;
			$edit->save("xajax_saveSettings(xajax.getFormValues(this.id))");
			
		} else {
			$edit->readOnly = true;
			$edit->addButton("Редактировать", "load('$app&edit=yes')");
		}
		//$edit->addControl("Язык по умолчанию:", "PROTECTED");
		//$edit->addControl("Валюта по умолчанию:", "PROTECTED");
		
		//$edit->back = $app;
		//$edit->save("xajax_saveModule(xajax.getFormValues(this.id), {m_name:'req',module_id:'req',visible:'req'})");
		$edit->firstColWidth = '250px';
		$edit->showTable();
		
	} elseif ($tab->activeTab == 2) {
		if (isset($_REQUEST['edit']) && $_REQUEST['edit'] != '') {
			$edit = new editTable('custom'); 
			$edit->SQL  = "SELECT id,
								 `code`,
								 `value`,
								 system_name,
								 visible
							FROM core_settings 
							WHERE is_custom_sw='Y'
							AND id='{$_REQUEST['edit']}'";
			if ($_REQUEST['edit']) {
				$edit->addControl("Ключ:", "PROTECT");
			} else {
				$edit->addControl("Ключ:", "EDIT", "", "", "", true);
			}
			$edit->addControl("Значение:", "EDIT");
			$edit->addControl("Описание:", "EDIT", "size=\"60\"");
			$edit->addButtonSwitch('visible', $this->db->fetchOne("SELECT 1 FROM core_settings WHERE visible = 'Y' AND id=? LIMIT 1", $_GET['edit']));
			$edit->firstColWidth = '250px';
			$edit->back = $app . "&tab_settings=2";
			$edit->save("xajax_saveCustomSettings(xajax.getFormValues(this.id))");
			$edit->showTable();
		}
		$list = new listTable('custom');  
	
		$list->SQL = "SELECT `id`,
							 `code`,
							 value,
							 system_name,
							 visible
						FROM `core_settings`
						WHERE is_custom_sw='Y'
						";
		$list->addColumn("Ключ", "", "TEXT");
		$list->addColumn("Значение", "", "TEXT");
		$list->addColumn("Описание", "", "TEXT");
		$list->addColumn("", "1%", "STATUS_INLINE", "core_settings.visible");
		
		$list->paintCondition	= "'TCOL_04' == 'N'";
		$list->paintColor		= "ffffee";
		
		$list->addURL 			= $app . "&edit=0&tab_settings=2";
		$list->editURL 			= $app . "&edit=TCOL_00&tab_settings=2";
		$list->deleteKey		= "core_settings.id";
		
		$list->showTable();
	} elseif ($tab->activeTab == 3) {
		if (isset($_GET['edit']) && $_GET['edit'] != '') {
			$edit = new editTable('personal');
			$edit->SQL  = "SELECT id,
								 `code`,
								 `value`,
								 system_name,
								 visible
							FROM core_settings
							WHERE is_personal_sw='Y'
							AND id='{$_GET['edit']}'";
			if ($_GET['edit']) {
				$edit->addControl("Ключ:", "PROTECT");
			} else {
				$edit->addControl("Ключ:", "EDIT", "", "", "", true);
			}
			$edit->addControl("Значение:", "EDIT");
			$edit->addControl("Описание:", "EDIT", "size=\"60\"");
			$edit->addButtonSwitch('visible', $this->db->fetchOne("SELECT 1 FROM core_settings WHERE visible = 'Y' AND id=? LIMIT 1", $_GET['edit']));
			$edit->firstColWidth = '250px';
			$edit->back = $app . "&tab_settings=3";
			$edit->save("xajax_savePersonalSettings(xajax.getFormValues(this.id))");
			$edit->showTable();
		}
		$list = new listTable('personal');

		$list->SQL = "SELECT `id`,
							 `code`,
							 value,
							 system_name,
							 visible
						FROM `core_settings`
						WHERE is_personal_sw='Y'
						";
		$list->addColumn("Ключ", "", "TEXT");
		$list->addColumn("Значение", "", "TEXT");
		$list->addColumn("Описание", "", "TEXT");
		$list->addColumn("", "1%", "STATUS_INLINE", "core_settings.visible");

		$list->paintCondition	= "'TCOL_04' == 'N'";
		$list->paintColor		= "ffffee";

		$list->addURL 			= $app . "&edit=0&tab_settings=3";
		$list->editURL 			= $app . "&edit=TCOL_00&tab_settings=3";
		$list->deleteKey		= "core_settings.id";

		$list->showTable();
	}
$tab->endContainer();

