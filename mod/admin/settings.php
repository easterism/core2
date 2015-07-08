<?

$tab = new tabs('settings'); 
$tab->addTab($this->translate->tr("Настройки системы"), 				$app, 130);
$tab->addTab($this->translate->tr("Дополнительные параметры"), 		$app, 180);
$tab->addTab($this->translate->tr("Персональные параметры"), 		$app, 180);
//$tab->addTab("Языки",					$app, 130);
//$tab->addTab("Переводы",					$app, 130);
$tab->beginContainer($this->translate->tr("Конфигурация"));
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
			$edit->addControl($caps[$caption] . ":", "TEXT", 'size="30"');
		}
		if (!empty($_REQUEST['edit']) && $_REQUEST['edit'] == 'yes') {
			$edit->addButton($this->translate->tr("Отменить"), "load('$app')");
			$edit->back = $app;
			$edit->save("xajax_saveSettings(xajax.getFormValues(this.id))");
			
		} else {
			$edit->readOnly = true;
			$edit->addButton($this->translate->tr("Редактировать"), "load('$app&edit=yes')");
		}
		//$edit->addControl("Язык по умолчанию:", "PROTECTED");
		//$edit->addControl("Валюта по умолчанию:", "PROTECTED");
		
		//$edit->back = $app;
		//$edit->save("xajax_saveModule(xajax.getFormValues(this.id), {m_name:'req',module_id:'req',visible:'req'})");
		$edit->firstColWidth = '290px';
		$edit->showTable();
		
	} elseif ($tab->activeTab == 2) {
		if (isset($_GET['edit']) && $_GET['edit'] != '') {
			$edit = new editTable('custom');
            $refid = (int)$_GET['edit'];
			$edit->SQL  = "SELECT id,
								 `code`,
								 `value`,
								 system_name,
								 visible
							FROM core_settings 
							WHERE is_custom_sw='Y'
							AND id='{$refid}'";
			if ($_GET['edit']) {
				$edit->addControl($this->translate->tr("Ключ:"), "PROTECT");
			} else {
				$edit->addControl($this->translate->tr("Ключ:"), "EDIT", "", "", "", true);
			}
			$edit->addControl($this->translate->tr("Значение:"), "EDIT", "size=\"60\"");
			$edit->addControl($this->translate->tr("Описание:"), "EDIT", "size=\"60\"");
			$edit->addButtonSwitch('visible', $this->db->fetchOne("SELECT 1 FROM core_settings WHERE visible = 'Y' AND id=? LIMIT 1", $refid));
			$edit->firstColWidth = '250px';
			$edit->back = $app . "&tab_settings=2";
			$edit->save("xajax_saveCustomSettings(xajax.getFormValues(this.id))");
			$edit->showTable();
		}
		$list = new listTable('custom');
		$list->table = 'core_settings';
		function trimAction($data)
		{
			$r = mb_substr($data['value'], 0, 180, "UTF-8");
			if ($r != $data['value']) {
				$r .= "   ...";
			} else {
				$r = $data['value'];
			}
			return $r;
		}
		$list->SQL = "
            SELECT `id`,
                   `code`,
                   value,
                   system_name,
                   visible
            FROM `core_settings`
            WHERE is_custom_sw = 'Y'
            ORDER BY seq
        ";
		$list->addColumn($this->translate->tr("Ключ"), "", "TEXT");
		$list->addColumn($this->translate->tr("Значение"), "", "FUNCTION", "", "trimAction");
		$list->addColumn($this->translate->tr("Описание"), "", "TEXT");
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
				$edit->addControl($this->translate->tr("Ключ:"), "PROTECT");
			} else {
				$edit->addControl($this->translate->tr("Ключ:"), "EDIT", "", "", "", true);
			}
			$edit->addControl($this->translate->tr("Значение:"), "EDIT");
			$edit->addControl($this->translate->tr("Описание:"), "EDIT", "size=\"60\"");
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
		$list->addColumn($this->translate->tr("Ключ"), "", "TEXT");
		$list->addColumn($this->translate->tr("Значение"), "", "TEXT");
		$list->addColumn($this->translate->tr("Описание"), "", "TEXT");
		$list->addColumn("", "1%", "STATUS_INLINE", "core_settings.visible");

		$list->paintCondition	= "'TCOL_04' == 'N'";
		$list->paintColor		= "ffffee";

		$list->addURL 			= $app . "&edit=0&tab_settings=3";
		$list->editURL 			= $app . "&edit=TCOL_00&tab_settings=3";
		$list->deleteKey		= "core_settings.id";

		$list->showTable();
	}
$tab->endContainer();

