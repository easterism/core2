<?

$tab = new tabs('roles'); 
//$tab->addTab("Доступ по умолчанию",			$app, 130);
$tab->beginContainer("Роли и доступ");
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
			$edit->addControl("Название:", "TEXT", "maxlength=\"255\" size=\"60\"", "", "", true);
			$edit->addControl("Краткое описание:", "TEXTAREA", "class=\"fieldRolesShortDescr\"", "", "");
			$edit->addControl("Позиция в иерархии:", "TEXT", "maxlength=\"3\" size=\"2\"", "", "", true);
			$SQL = "SELECT * 
					  FROM (
						(SELECT m_id, m_name, module_id, m.seq, m.access_add
						  FROM core_modules AS m
						  WHERE visible='Y'
						  ORDER BY seq)
						UNION ALL 
						(SELECT `sm_id` AS m_id,
								 CONCAT(m_name, ' / ', sm_name) AS m_name,
								 CONCAT(m.module_id, '-', s.sm_key) AS module_id,
								 m.seq,
								 s.access_add
							FROM `core_submodules` AS s
								 INNER JOIN core_modules AS m ON m.m_id = s.m_id AND m.visible='Y'
							WHERE sm_id > 0 AND s.visible='Y'
						   ORDER BY m.seq, s.seq)
					   ) AS a ORDER BY 4";
			$res = $this->db->fetchAll($SQL);
			
			$html = '<table>';
			
			$this->tpl->loadTemplate("core2/mod/admin/html/role_access.tpl");
			
			$access = $this->tpl->parse();
			$tplRAAdd = file_get_contents("core2/mod/admin/html/role_access_add.tpl");
			foreach ($res as $value) {
				$accessAddHTML = '';
				if ($value['access_add']) {
					$accessAddData = unserialize(base64_decode($value['access_add']));
					if ($accessAddData) {
						foreach ($accessAddData as $keyAD => $valueAD) {
							if ($keyAD) {
								$this->tpl->setTemplate($tplRAAdd);
								$this->tpl->assign('NAME_ACTION', $keyAD);
								$this->tpl->assign('TYPE_ID', ($keyAD));
								$this->tpl->assign('MODULE_ID', $value['module_id']);
								$accessAddHTML .= $this->tpl->parse();
							}
						}
					}
				}
				$html .= '<tr><td class="roleModules">' . $value['m_name'] . '</td>'.
							'<td>' . str_replace("MODULE_ID", $value['module_id'], $access) . $accessAddHTML . '</td>'.
							'</tr>';
			
			}
			$html .= '</table>';
			if ($_GET['edit']) {
				$acl = Zend_Json::encode(unserialize($this->dataRoles->find($_GET['edit'])->current()->access));
				 				
				$html .= '<script>ro.setDefault(' . $acl . ')</script>';
			} else {
				$html .= '<script>ro.setDefaultNew()</script>';
			}
			$edit->addControl("Доступ к модулям:", "CUSTOM", $html);
						
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
		$list->addColumn("Роль", "", "TEXT");
		$list->addColumn("Описание", "", "TEXT");
		$list->addColumn("Иерархия", "1%", "TEXT");
		$list->addColumn("", "1%", "STATUS");
		
		$list->paintCondition	= "'TCOL_05' == 'N'";
		$list->paintColor		= "ffffee";
		
		$list->addURL 			= $app . "&edit=0";
		$list->editURL 			= $app . "&edit=TCOL_00";
		
		$list->deleteKey		= "core_roles.id";
		
		$list->showTable();
		
	} elseif ($tab->activeTab == 2) {
		
	}
$tab->endContainer();

