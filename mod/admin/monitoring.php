<?
require_once('core2/inc/classes/Templater3.php');
$session = new Zend_Session_Namespace('monitoring');
if (isset($_GET['lines'])) {
	$lines = (int)$_GET['lines'];
} elseif (isset($session->lines)) {
	$lines = $session->lines;
} else {
	$lines = 200;
}
if (isset($_GET['search'])) {
	$search = $_GET['search'];
} elseif (isset($session->search)) {
	$search = $session->search;
} else {
	$search = "";
}
$session->lines = $lines;
$session->search = $search;
if (isset($_GET['download'])) {
	if ($this->config->log->system->writer == 'file') {
		$data = $this->getLogsData('file', $lines, $search);
		$body = $data['body'];
	} else {
		$data = $this->getLogsData('db', $lines, $search);
		$body = $data['body'];
	}
	header("Content-Type: application/octet-stream");
	header("Accept-Ranges: bytes");
	//header("Content-Length: 1000");
	header("Content-Disposition: attachment; filename=\"monitoring " . date("Y-m-d H:i:s") . ".txt.gz");
	echo gzencode($body);
	exit;
}

$tab = new tabs($this->resId); 

$title = $this->translate->tr("Мониторинг");
$tab->addTab($this->translate->tr("Активные пользователи"), 		$app, 150);
$tab->addTab($this->translate->tr("История посещений"), 			$app, 150);
$tab->addTab($this->translate->tr("Журнал запросов"), 			$app, 150);
$tab->addTab($this->translate->tr("Архив журнала"), 			$app, 150);
//Tool::fb($_SERVER);
$tab->beginContainer($title);

	if ($tab->activeTab == 1) {
		if (isset($_GET['edit']) && $_GET['edit'] != '') {
			
			
		} else {
			if (!empty($_GET['kick'])) {
				$sid = $this->db->fetchOne("SELECT sid
										  FROM core_session
										 WHERE id = ?
										 ", $_GET['kick']);
				if ($sid) {
					if ($this->config->session && $this->config->session->save_path) {
						unlink($this->config->session->save_path . '/sess_' . $sid);
					}
					$where = $this->db->quoteInto('id = ?', $_GET['kick']);
					$this->db->update('core_session', array('logout_time' => new Zend_Db_Expr('NOW()')), $where);
				}
			}
			$sLife = $this->getSetting("session_lifetime");
			if (!$sLife) {
				$sLife = ini_get('session.gc_maxlifetime');
			}
			$this->printJs("core2/mod/admin/monitor.js");
			
			$list = new listTable($this->resId); 
			$list->addSearch($this->translate->tr("Пользователь"), "u_login", "TEXT");
			$list->addSearch($this->translate->tr("Время входа"), "login_time", "DATE");
			$list->addSearch($this->translate->tr("Время последней активности"), "last_activity", "DATE");
			$list->addSearch("IP", "ip", "TEXT");
			//$list->addSearch("Отображать под", "r.boss", "text");
			$list->SQL = "SELECT id,
								sid,
								u_login, 
								login_time, 
								last_activity,
								COALESCE(ip, 'не определен') AS ip,
								NULL AS kick
							FROM core_session AS s
								 JOIN core_users AS u ON u.u_id = s.user_id
							WHERE logout_time IS NULL
							  AND (NOW() - last_activity > $sLife)=0 ADD_SEARCH
						   ORDER BY login_time DESC";
			$list->addColumn($this->translate->tr("Сессия"), "", "TEXT");
			$list->addColumn($this->translate->tr("Пользователь"), "", "TEXT");
			$list->addColumn($this->translate->tr("Время входа"), "", "DATETIME");
			$list->addColumn($this->translate->tr("Время последней активности"), "", "DATETIME");
			$list->addColumn("IP", "1%", "TEXT");
			$list->addColumn("", "1%", "BLOCK");

			$list->getData();
			foreach ($list->data as $k => $val) {
				$list->data[$k][6] = '<img src="core2/html/' . THEME . '/img/link_break.png" title="выкинуть из системы" onclick="kick(' . $val[0] . ')">';
			}

			$list->noCheckboxes = 'yes';
			$list->showTable();
		}
	}
	elseif ($tab->activeTab == 2) { // История посещений
		if (!empty($_GET['show'])) {
			$show = (int)$_GET['show'];
			$res = $this->db->fetchRow("SELECT u_login, up.lastname, up.firstname, up.middlename
										  FROM core_users AS u
											   JOIN core_users_profile AS up ON up.user_id = u.u_id
										WHERE u_id = ? LIMIT 1", $show);
			$name = $res['firstname'];
			if (!empty($name)) {
				$name .= ' ' . $res['lastname'];
			} else {
				$name = $res['u_login'];
			}
			if (!empty($name)) $name = '<b>' . $name . '</b>';
			echo '<div>Пользователь ' . $name . '</div>';
			$res = $this->db->fetchRow("SELECT DATE_FORMAT(login_time, '%d-%m-%Y %H:%i:%s') AS login_time, ip
										FROM core_session 
										WHERE user_id = ? 
										ORDER BY login_time DESC LIMIT 1", $show);
			echo '<div>Последний раз заходил <b>' . $res['login_time'] . '</b> с IP адреса <b>' . $res['ip'] . '</b></div>';
			$list = new listTable($this->resId . 'xxx2'); 
			$list->addSearch($this->translate->tr("Время входа"), "login_time", "DATE");
			$list->addSearch("IP", "ip", "TEXT");
			$list->sqlSearch[] = array('Y' => 'Да', 'N' =>'Нет');
			$list->addSearch("Криптосредства", "crypto_sw", "LIST");
			//$list->addSearch("Отображать под", "r.boss", "text");
			$list->SQL = "SELECT user_id,
								 login_time,
								 COALESCE(logout_time, 'окончание сессии') AS _out,
								 COALESCE(ip, 'не определен') AS ip
							FROM `core_session` AS s
							WHERE user_id='{$show}'
							ADD_SEARCH
							ORDER BY login_time DESC";
			$list->addColumn("Время входа", "", "DATETIME");
			$list->addColumn("Время выхода", "", "TEXT");
			$list->addColumn("IP", "1%", "TEXT");

			//$list->editURL 			= $app . "&show=TCOL_00&tab_" . $this->resId . "=" . $tab->activeTab;
			$list->noCheckboxes = 'yes';
			$list->showTable();
		} else {
			$list = new listTable($this->resId . 'xxx2'); 
			$list->addSearch("Пользователь", "u_login", "TEXT");
			$list->addSearch("Время последней активности", "last_activity", "DATE");
			$list->addSearch("IP", "ip", "TEXT");
			//$list->addSearch("Отображать под", "r.boss", "text");
			$list->SQL = "SELECT DISTINCT user_id,
								 u.u_login,
								 last_activity, 
								 COALESCE(ip, 'не определен') AS ip
							FROM `core_session` AS s
								 JOIN core_users AS u ON u.u_id=user_id
							WHERE NOT EXISTS (SELECT 1 FROM core_session WHERE user_id=s.user_id AND last_activity > s.last_activity)
							ADD_SEARCH
							ORDER BY last_activity DESC";
			$list->addColumn("Пользователь", "", "TEXT");
			$list->addColumn("Время последней активности", "", "DATETIME");
			$list->addColumn("IP", "1%", "TEXT");

			$list->editURL 		= $app . "&show=TCOL_00&tab_" . $this->resId . "=" . $tab->activeTab;
			$list->noCheckboxes = 'yes';
			$list->showTable();
		}
	}
	elseif ($tab->activeTab == 3) { // журнал запросов
		$this->printJs("core2/mod/admin/book.js");

		if ($this->config->log->system->writer == 'file') {
			if ($this->config->log->system->file) {
				$data = $this->getLogsData('file', $lines, $search);
				$count_lines = $data['count_lines'];
				$body = $data['body'];
			} else {
				echo "В конфиге не задан файл хранения логов";
			}
		} else {
			$data = $this->getLogsData('db', $lines, $search);
			$count_lines = $data['count_lines'];
			$body = $data['body'];
		}
		$tpl = new Templater3("core2/mod/admin/html/monitoring.tpl");
		$tpl->assign('[LINES_VALUE]', $lines);
		$tpl->assign('[SEARCH_VALUE]', htmlspecialchars($search));
		$tpl->assign('[BODY]', $body);
		$tpl->assign('[INFO]', "Строк: <b>" . Tool::commafy($count_lines) . "</b>&nbsp;&nbsp;&nbsp;");
		echo $tpl->render();
	}
	elseif ($tab->activeTab == 4) {
		
		$zipFolder = $this->config->system->path_archive;
		if (empty($zipFolder)) {
			throw new Exception("Не указана директория для архивов. Ее нужно указать в конфигурационном файле conf.ini с ключом 'system.path_archive'");

		} elseif ( ! is_dir($zipFolder)) {
			throw new Exception("Директория не найдена. Ключ: system.path_archive = '$zipFolder'");
		}

		/* Загрузка файла */
		if (isset($_GET['download'])) {
			$fileName = $_GET['download'];
			$fileForDownload = $zipFolder . "/" . $fileName;

			$h = fopen($fileForDownload, 'rb');
			if (!$h) {
				throw new Exception("Файл не найден!");
			}
			$fs = filesize($fileForDownload);
			$md5_sum = md5_file($fileForDownload);
			$fc = fread($h, $fs);
			fclose($h);
			header("Content-Length: $fs");
			header("Content-md5: " . $md5_sum);
			header("Connection: close");
			header("Content-Disposition: attachment; filename=" . $fileName);
			header("Content-type: application/zip");
			ob_end_clean();
			echo $fc;
			die;
		}

		if (!is_writable($zipFolder)) {
			throw new Exception("Директория '$zipFolder' защищена от записи.");
		}

		if (isset($_GET['edit'])) {

			ini_set('memory_limit', '512M');
			ini_set("max_execution_time", "0");

			$tempFile = $this->config->temp . "/test.txt";
			$zipFile = $zipFolder . "/" . date("d_m_YvH-i-s") . ".zip";

			$this->db->beginTransaction();
			try {

				/* Запись во временный файл */
				$f = fopen($tempFile, 'w');
				if (!$f) {
					throw new Exception("Ошибка записи во временный файл");
				}
				$zip = new ZipArchive();
				if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
					throw new Exception("Ошибка создания архива");
				}
				$lastId = 0;
				for ($i = 0; $i < 100; $i++) {
					$res = $this->db->fetchAll("SELECT l.id,
													   s.ip,
													   u.u_login,
													   l.query,
													   l.action,
													   l.lastupdate
												   FROM core_log AS l
													  LEFT JOIN core_users AS u ON u.u_id = l.user_id
													  LEFT JOIN core_session AS s ON s.sid = l.sid
												   ORDER BY l.id ASC
												   LIMIT 1000");
					if ($res) {
						$endId = end($res);
						$lastId = $endId['id'];

						foreach ($res as $key => $val) {
							$strData = implode(";", $val);
							fwrite($f, $key . " " . $strData . chr(10));

						}
						//удаление из таблицы
						$where = "id<=" . $lastId;
						$this->db->delete("core_log", $where);
						unset($res);
					}
				}
				fclose($f);

				/* Создание zip- архива */
				$zip->addFile($tempFile, "archive.txt");
				$zip->close();

				$this->db->commit();

			} catch (Exception $e) {
				$this->db->rollback();
				if (is_file($zipFile)) unlink($zipFile);
				echo $e->getMessage();
			}
			
		}

		
		
		$list = new listTable($this->resId . 'archive');
		$list->SQL = "SELECT 1";
		$list->addColumn("Имя файла", "", "TEXT");
		$list->addColumn("Дата создания архива", "", "DATETIME");
		$list->addColumn("Загрузить", "5%", "BLOCK");
		$data = $list->getData();

		$dir = opendir($zipFolder);
		if (!$dir) {
			throw new Exception("Не могу прочитать директорию '$zipFolder'. Проверьте права доступа.");
		}
		$dataForList = array();
		$i = 0;
		
		while ($file = readdir($dir))
		{
			$i++;
			if ($file != "." && $file != ".." && !strpos($file, "svn"))
				
				if (!is_dir($zipFolder . "/" . $file))
				{					
					
					$file_create = stat($zipFolder."/".$zipFile); 
					$dataForList[$i][] = $i;
					$dataForList[$i][] = $file;						
					$dataForList[$i][] = date("Y-m-d H:i:s", filectime($zipFolder . "/" . $file));
					$dataForList[$i][] = '<a href="index.php?module=admin&action=monitoring&tab_admin_monitoring=4&download='.$file.'"><img src="core2/html/'.THEME.'/img/templates_button.png" border="0"/></a>';
					
				}
		}
		
		closedir($dir);
		$list->data = $dataForList;
		$list->classText['ADD'] = "Сформировать архив"; 
		$list->addURL 			= $app . "&tab_admin_monitoring=4&edit=0"; 		
		$list->showTable();
		
	
		
		
	}
$tab->endContainer();
