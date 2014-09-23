<?

$tab = new tabs('enum'); 

$title = "Справочники";
if (isset($_GET['edit']) && $_GET['edit'] == '0') {
	$title = "Создание нового справочника";
}
if ($_GET['edit']) {
	$name = $this->dataEnum->find($_GET['edit'])->current()->name;
	$title = "Редактирование справочника";
}
$tab->beginContainer($title);
	if ($tab->activeTab == 1) {
		$this->printJs("core2/mod/admin/mod.js");
		if (isset($_GET['edit']) && $_GET['edit'] != '') {			
			$edit = new editTable('enum'); 
			//$edit->firstColWidth = 200;
			$edit->SQL  = "SELECT id,
								 name,
								 global_id,
								 custom_field								 								 
							  FROM core_enum
							 WHERE id = '" . $_GET['edit'] . "' 
							 AND parent_id IS NULL";
			$edit->addControl("Название справочника:", "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
			if ($_GET['edit'] > 0) {
				$edit->addControl("Идентификатор:", "PROTECTED");
			} else {			 
				$edit->addControl("Идентификатор справочника:", "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
			}
			$list_custom = "";
			$tpl = new Templater("core2/mod/admin/html/custom_enum_field.tpl");
			$enums = $this->dataEnum->fetchFields(array('global_id', 'name'), "parent_id IS NULL")->toArray();
			$tpl->fillDropDown('yyy', $enums, '');
			$custom_field = $tpl->parse();
			
			if ($_GET['edit']) {
				$res = $this->dataEnum->find($_GET['edit'])->current()->custom_field;
				$custom_fields = unserialize(base64_decode($res));
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
			}
			
			$custom = '<div id="xxx">' . $list_custom . '</div>
			<div><span id="new_attr" class="newFieldEnum" onclick="en.newEnumField()">Новое поле</span></div>';
			$edit->addControl("Дополнительные поля:", "CUSTOM", $custom);
			$edit->addButtonSwitch('is_active_sw', $this->dataEnum->exists("is_active_sw = 'Y' AND id=?", $_GET['edit']));
			
			$edit->back = $app;
			$edit->addButton("Вернуться к списку справочников", "load('$app')");
			$edit->save("xajax_saveEnum(xajax.getFormValues(this.id))");
			
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

			if ($_GET['edit']) {
				$tab->beginContainer("Перечень значений справочника \"" . $name . "\"");

				$fields_sql = "";
				$fields = $custom_fields;

				if (isset($_GET['add']) && $_GET['add'] != '') {					
					$edit = new editTable('enumxxxur');

					$res2 = $this->dataEnum->find($_GET['add'])->current()->custom_field;
					$arr_fields = array();

					/* Формирование массива кастомных полей из строки */

					if (!empty($res2)) {
						$name_val = explode(":::", $res2);
						foreach ($name_val as $v) {
							$temp  = explode("::", $v);
							$arr_fields[$temp[0]] = (!empty($temp[1])) ? $temp[1] : "";

						}
					}

					$edit->addControl("Значение:", "TEXT", "maxlength=\"128\" size=\"60\"", "", "");
					if (is_array($fields) && count($fields)) {
						$fields_sql = "\n";
						foreach ($fields as $key => $val) {
							$fields_sql .= "'{$arr_fields[$val['label']]}' AS id_$key,\n";

							if ($val['type'] == 1) $type = 'TEXT';
							elseif ($val['type'] == 2) {
								$type = 'LIST';
								$edit->selectSQL[] = $this->getEnumDropdown($val['enum'], true, true);
							} elseif ($val['type'] == 3) {
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
							$edit->addControl($val['label'], $type, "", "", "");
						}
						$fields_sql = rtrim($fields_sql);
						$edit->addParams("custom_fields", base64_encode(serialize($fields)));
					}
				
					$edit->SQL  = "SELECT  id,
										   name, $fields_sql										   									   
										   is_default_sw,										   										   										   
										   is_active_sw,
										   parent_id										   
									  FROM core_enum
									  WHERE id = '{$_GET['add']}'";

					$edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет'); 
					$edit->addControl("По умолчанию:", "RADIO", "", "", "N");
					$edit->selectSQL[] = array('Y' => 'вкл.', 'N' => 'выкл.');
					$edit->addControl("Статус:", "RADIO", "", "", "Y");
					$edit->addControl("", "HIDDEN", "", "", $_GET['edit'], true);

					$edit->back = $app . "&edit=" . $_GET['edit'];
					$edit->addButton("Отменить", "load('{$app}&edit={$_GET['edit']}')");
					$edit->save("xajax_saveEnumValue(xajax.getFormValues(this.id))");
					
					$edit->showTable();
				}
				//ENUM dtl list
				$list = new listTable('enumxxx3');
				$list->table = "core_enum";
				$list->addSearch('Значение', 'name', 'TEXT');
				$list->addColumn("Значение", "", "TEXT");
				$list->addColumn("По умолчанию", "2%", "TEXT");

				$fields_sql = '';
				if (is_array($fields) && count($fields)) {
					$fields_sql = "\n";
					foreach ($fields as $key => $val) {
						$fields_sql .= "'id_{$val['label']}',\n";
						$list->addColumn($val['label'], "", "TEXT");
					}
					$fields_sql = rtrim($fields_sql);

				}
			
				$list->SQL = "SELECT id,
									 name,
									 CASE is_default_sw WHEN 'Y' THEN 'Да' ELSE 'Нет' END AS is_default_sw, $fields_sql
									 seq,
									 is_active_sw,
								 	 custom_field
								FROM core_enum
							   WHERE parent_id='{$_GET['edit']}' ADD_SEARCH
							   ORDER BY seq, name";

				$list->addColumn("Очередность", "1%", "TEXT");
				$list->addColumn("", "1%", "STATUS_INLINE", 'core_enum.is_active_sw');

				$list->paintCondition	= "'TCOL_03' == 'N'";
				$list->paintColor		= "ffffee";
				
				$list->addURL 			= $app . "&edit=" . $_GET['edit'] . "&add=0";
				$list->editURL 			= $app . "&edit=" . $_GET['edit'] . "&add=TCOL_00";
				$list->deleteKey		= "core_enum.id";

				$list->getData();
				foreach ($list->data as $key => $value) {
					$name_val = explode(":::", end($value));
					foreach ($name_val as $v) {
						$temp  = explode("::", $v);
						$k = array_search('id_' . $temp[0], $value);
						if ($k) $value[$k] = $temp[1];
					}
					foreach ($value as $k => $v) {
						if (strpos($v, 'id_') === 0) {
							$value[$k] = '';
						}
					}
					$list->data[$key] = $value;
				}
				$list->showTable();
				$tab->endContainer();
			}
		} else {
			
			$list = new listTable('enum');
			$list->addSearch('Идентификатор', 'global_id', 'TEXT');
			$list->addSearch('Название справочника', 'name', 'TEXT');

			$list->SQL = "SELECT id,
								 global_id,
								 name,
								 CASE WHEN custom_field IS NOT NULL THEN 'Да' END AS custom,
								 (SELECT COUNT(1) FROM core_enum WHERE parent_id = e.id) AS co,
								 is_active_sw
							FROM core_enum AS e
							WHERE parent_id IS NULL ADD_SEARCH
						   ORDER BY `name`";
			$list->addColumn("Идентификатор", "", "TEXT");
			$list->addColumn("Название справочника", "70%", "TEXT");
			$list->addColumn("Дополпительные поля", "", "TEXT");
			$list->addColumn("Значений", "20%", "NUMBER");
			$list->addColumn("", "1%", "STATUS_INLINE", 'core_enum.is_active_sw');
			
			$list->paintCondition	= "'TCOL_04' == 'N'";
			$list->paintColor		= "fafafa";
			
			$list->addURL 			= $app . "&edit=0";
			$list->editURL 			= $app . "&edit=TCOL_00";
			$list->deleteKey		= "core_enum.id";
			
			$list->showTable();
		}
	}
$tab->endContainer();

