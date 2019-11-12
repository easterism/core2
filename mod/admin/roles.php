<?

$tab = new tabs('roles'); 
//$tab->addTab("Доступ по умолчанию",			$app, 130);
$tab->beginContainer($this->translate->tr("Роли и доступ"));
	if ($tab->activeTab == 1) {
		if (isset($_GET['edit']) && $_GET['edit'] != '') {
			$this->printJs("core2/mod/admin/role.js");
			$edit = new editTable('roles'); 
			$edit->SQL  = "SELECT  id,
								   name,
								   description,
								   position,
								   access
							  FROM core_roles
							 WHERE id = '" . $_GET['edit'] . "'";
			$edit->addControl($this->translate->tr("Название:"), "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
			$edit->addControl($this->translate->tr("Краткое описание:"), "TEXTAREA", "class=\"fieldRolesShortDescr\"", "", "");
			$edit->addControl($this->translate->tr("Позиция в иерархии:"), "TEXT", "maxlength=\"3\" size=\"2\"", "", "", true);
			$SQL = "SELECT * 
					  FROM (
						(SELECT m_id, m_name, module_id, CAST(m.seq AS CHAR), m.access_add
						  FROM core_modules AS m
						  WHERE visible='Y')
						UNION ALL 
						(SELECT sm_id AS m_id,
								 CONCAT(m_name, ' / ', sm_name) AS m_name,
								 CONCAT(m.module_id, '-', s.sm_key) AS module_id,
								 CONCAT(m.seq, '-', s.seq) AS seq,
								 s.access_add
							FROM core_submodules AS s
								 INNER JOIN core_modules AS m ON m.m_id = s.m_id AND m.visible='Y'
							WHERE sm_id > 0
							AND s.visible='Y')
					   ) AS a
					   ORDER BY 4";
			$res = $this->db->fetchAll($SQL);
			
			$html = '<table>';

			require_once DOC_ROOT . '/core2/inc/classes/Templater3.php';
			$tpl = new Templater3("core2/mod/admin/html/role_access.tpl");

			foreach ($res as $value) {
				if ($value['access_add']) {
					$accessAddData = @unserialize(base64_decode($value['access_add']));
					if ($accessAddData) {
						foreach ($accessAddData as $keyAD => $valueAD) {
							if ($keyAD) {
								$tpl->custom->assign('NAME_ACTION', $keyAD);
                                $tpl->custom->assign('MODULE_ID',   $value['module_id']);
                                $tpl->custom->assign('TYPE_ID',     ($keyAD));
                                $tpl->custom->reassign();
							}
						}
					}
				}
				$html .= '<tr><td class="roleModules">' . $value['m_name'] . '</td>'.
							'<td>' . str_replace("MODULE_ID", $value['module_id'], $tpl->render()) . '</td>'.
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
						
			$edit->back = $app;
			$edit->save("xajax_saveRole(xajax.getFormValues(this.id))");
			
			$edit->showTable();
		}
		$list = new listTable('roles');

		$list->table = "core_roles";
	
		$list->SQL = "SELECT `id`,
							 `name`,
							 description,
							 position,
							 is_active_sw
						FROM `core_roles` 
						ORDER BY position";
		$list->addColumn($this->translate->tr("Роль"), "", "TEXT");
		$list->addColumn($this->translate->tr("Описание"), "", "TEXT");
		$list->addColumn($this->translate->tr("Иерархия"), "1%", "TEXT");
		$list->addColumn("", "1%", "STATUS");
		
		$list->paintCondition	= "'TCOL_04' == 'N'";
		$list->paintColor		= "ffffee";
		
		$list->addURL 			= $app . "&edit=0";
		$list->editURL 			= $app . "&edit=TCOL_00";
		
		$list->deleteKey		= "core_roles.id";
		
		$list->showTable();
		
	} elseif ($tab->activeTab == 2) {
		
	}
$tab->endContainer();

