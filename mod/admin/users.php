<?
//echo Tool::pass_salt(md5(''));

$tab = new tabs('users'); 
$title = "Справочник пользователей системы";
if (isset($_GET['edit']) && $_GET['edit'] == '0') {
	//$tab->addTab("Пользователи", 		$app, 130);
	$title = "Создание нового пользователя";
}
if (!empty($_GET['edit'])) {
	$user = $this->dataUsers->find($_GET['edit'])->current();
	$title = "Редактирование пользователя \"" . $user->u_login . "\"";
}
$tab->beginContainer($title);

	if ($tab->activeTab == 1) {
		
		if (isset($_GET['edit']) && $_GET['edit'] != '') {
			$edit = new editTable('user'); 
			$u_login = '
			u_login,';
			if ($_GET['edit']) {
				$u_login = '';
			} else {
				$edit->addControl("Логин:", "TEXT", "maxlength=\"60\" size=\"60\"", "", "", true);
			}
			$certificate = '';
			if ($_GET['edit']) {
				/*$names = $this->db->fetchRow("SELECT lastname,firstname,middlename FROM core_users_profile WHERE user_id=?", $_GET['edit']);
				'{$names['lastname']}' AS lastname,
								   '{$names['lastname']}' AS firstname,
								   '{$names['lastname']}' AS middlename,*/
				$certificate = $user->certificate;

			}
			$htmlCertificate = '<br/><textarea cols="40" rows="7" name="control[certificate_ta]">' . ($certificate) . '</textarea>';
			
			$send_info_sw = '';
			if ( $_GET['edit']  == 0) {
				$send_info_sw = ',
					NULL AS send_info_sw
				';
			}
			$edit->SQL  = "SELECT  u_id, $u_login
								   email,
								   role_id,
								   lastname,
								   firstname,
								   middlename,
								   u_pass,
								   certificate,
								   is_email_wrong,
								   is_pass_changed,
								   is_admin_sw {$send_info_sw}
							  FROM core_users
							  	   LEFT JOIN core_users_profile AS p ON p.user_id=u_id
							 WHERE `u_id` = '" . $_GET['edit'] . "'";
			
			$edit->addControl("Email:", "TEXT", "maxlength=\"60\" size=\"60\"", "", "");
			$edit->selectSQL[] = "SELECT id, name FROM 
									(SELECT NULL AS id, NULL AS name, NULL AS position 
										UNION ALL 
									SELECT id, name, position FROM core_roles WHERE is_active_sw='Y') AS a
								 ORDER BY position ASC";
			$edit->addControl("Роль:", "LIST", "", "", "");
			
			$edit->addControl("Фамилия:", "TEXT", "maxlength=\"20\" size=\"40\"", "", "");
			$edit->addControl("Имя:", "TEXT", "maxlength=\"20\" size=\"40\"", "", "", true);
			$edit->addControl("Отчество:", "TEXT", "maxlength=\"20\" size=\"40\"", "", "");
//			$passHash = "
//			<script>
//				$(document).ready(function(){
//					var ofFunc = $('#main_user_mainform').attr('onSubmit');
//					var hash = \"if ($('#main_useru_pass').val() != '') \{$('#main_useru_pass').val(hex_md5($('#main_useru_pass').val()));\";										
//					hash += \"$('#main_useru_pass2').val(hex_md5($('#main_useru_pass2').val()));}\";										
//					$('#main_user_mainform').attr('onSubmit', hash + ofFunc);
//				})
//			</script>
//			";
			$passHash = '';
			$edit->addControl("Пароль:", "PASSWORD", "", $passHash, "", true);			
			$edit->addControl("Сертификат:", "FILE", "cols=\"70\" rows=\"10\"", $htmlCertificate, "");
			
			$edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет'); 
			$edit->addControl("Неверный email:", "RADIO", "", "", "N", true);
			$edit->selectSQL[] = array('N' => 'да', 'Y' => 'нет');
			$edit->addControl("Предупреждение о смене пароля:", "RADIO", "", "", "N", true);

			$edit->selectSQL[] = array('Y' => 'да', 'N' => 'нет');
			$edit->addControl("Администратор безопасности (полный доступ):", "RADIO", "", "", "N", true);

			$edit->selectSQL[] = array('Y' => '');
			$edit->addControl("отправить информацию о пользователе на email", "CHECKBOX", "", "", "0");

			$edit->addButtonSwitch('visible', $this->dataUsers->exists("visible = 'Y' AND u_id=?", $_GET['edit']));
			
			$edit->back = $app;
			$edit->addButton("Вернуться к списку пользователей", "load('$app')");
			$edit->save("xajax_saveUser(xajax.getFormValues(this.id))");
			
			$edit->showTable();
			
		} else {
			$errorNamespace = new Zend_Session_Namespace('Error');
			if ($errorNamespace->ERROR) {
				echo '<div class="error" style="display: block">' . $errorNamespace->ERROR . '</div>';
			}

			$list = new listTable('user');
			$list->addSearch("Логин", "u.u_login", "TEXT");
			$list->addSearch("Фамилия", "up.lastname", "TEXT");
			$list->addSearch("Имя", "up.firstname", "TEXT");
			$list->SQL = "SELECT `u_id`,
								 `u_login`,
								 CONCAT_WS(' ' ,up.lastname, up.firstname, up.middlename),
								 email,
								 r.name,
								 u.date_added,
								 CASE u.`is_pass_changed` WHEN 'N' THEN 'Да' END AS is_pass_changed,
								 CASE u.`is_email_wrong` WHEN 'Y' THEN 'Да' END AS is_email_wrong,
								 CASE u.`is_admin_sw` WHEN 'Y' THEN 'Да' END AS is_admin_sw,
								 u.visible
							FROM core_users AS u
								 LEFT JOIN core_users_profile AS up ON up.user_id = u.u_id
								 LEFT JOIN core_roles AS r ON r.id = u.role_id
							WHERE u_id > 0 ADD_SEARCH
						   ORDER BY u.date_added DESC";
			$list->addColumn("Логин", "", "TEXT");
			$list->addColumn("Имя", "", "TEXT");
			$list->addColumn("Email", "2%", "TEXT");
			$list->addColumn("Роль", "", "TEXT");
			$list->addColumn("Дата регистрации", "", "DATE");
			$list->addColumn("Нужно сменить пароль", "1%", "TEXT");
			$list->addColumn("Неверный email", "1%", "TEXT");
			$list->addColumn("Админ", "1%", "TEXT");
			$list->addColumn("", "1%", "STATUS_INLINE", "core_users.visible");

			$list->paintCondition	= "'TCOL_08' == 'N'";
			$list->paintColor		= "fafafa";
			$list->fontColor		= "silver";

			$list->addURL 			= $app . "&edit=0";
			$list->editURL 			= $app . "&edit=TCOL_00";
			$list->deleteKey		= "core_users.u_id";
			$list->showTable();
		}
	}
	
$tab->endContainer();