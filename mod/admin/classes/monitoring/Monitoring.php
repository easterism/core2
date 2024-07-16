<?php
namespace Core2;

use Laminas\Session\Container as SessionContainer;
use Core2\Tool;
class Monitoring extends \Common
{

    public function __construct()
    {
        parent::__construct();

        $session = new SessionContainer('monitoring');


        if (isset($_GET['lines'])) {
            $lines = (int)$_GET['lines'];
        } elseif (isset($session->lines)) {
            $lines = $session->lines;
        } else {
            $lines = 50;
        }


        if (isset($_GET['search'])) {
            $search = $_GET['search'];
        } elseif (isset($session->search)) {
            $search = $session->search;
        } else {
            $search = "";
        }


        $session->lines  = $lines;
        $session->search = $search;
    }

    public function getOnline()
    {
        if ( ! empty($_GET['edit'])) {


        }
        else {

            $sLife = $this->getSetting("session_lifetime");
            if ( ! $sLife) {
                $sLife = ini_get('session.gc_maxlifetime');
            }
            $this->printJs("core2/mod/admin/assets/js/monitor.js");

            $list = new \listTable($this->resId);
            $list->addSearch($this->translate->tr("Пользователь"),               "u_login",       "TEXT");
            $list->addSearch($this->translate->tr("Время входа"),                "login_time",    "DATE");
            $list->addSearch($this->translate->tr("Время последней активности"), "last_activity", "DATE");
            $list->addSearch("IP",                                               "ip",            "TEXT");


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
							  AND (NOW() - last_activity > $sLife)=0 /*ADD_SEARCH*/
						   ORDER BY login_time DESC";

            $list->addColumn($this->translate->tr("Сессия"),                     "",   "TEXT");
            $list->addColumn($this->translate->tr("Пользователь"),               "",   "TEXT");
            $list->addColumn($this->translate->tr("Время входа"),                "",   "DATETIME");
            $list->addColumn($this->translate->tr("Время последней активности"), "",   "DATETIME");
            $list->addColumn("IP",                                               "1%", "TEXT");
            $list->addColumn("",                                                 "1%", "BLOCK");

            $list->getData();
            foreach ($list->data as $k => $val) {
                $list->data[$k][6] = '<img src="core2/html/' . THEME . '/img/link_break.png" title="' . $this->translate->tr('выкинуть из системы') . '" onclick="kick(' . $val[0] . ')">';
            }

            $list->noCheckboxes = 'yes';
            ob_start();
            $list->showTable();
            return ob_get_clean();
        }
    }

    public function getHistory()
    {
        $app = "index.php?module=admin&action=monitoring";
        ob_start();

        if ( ! empty($_GET['show'])) {
            $show = (int)$_GET['show'];
            $res  = $this->db->fetchRow("
                SELECT u_login, 
                       up.lastname, 
                       up.firstname, 
                       up.middlename
				FROM core_users AS u
				    LEFT JOIN core_users_profile AS up ON up.user_id = u.u_id
				WHERE u_id = ? 
                LIMIT 1
            ", $show);

            if ($res) {
                $name = $res['firstname'];

                if ( ! empty($name)) {
                    $name .= ' ' . $res['lastname'];
                } else {
                    $name = $res['u_login'];
                }

                if ( ! empty($name)) {
                    $name = '<b>' . $name . '</b>';
                }

            } else {
                $name = '';
            }


            echo "<div>{$this->translate->tr('Пользователь')} {$name}</div>";

            $res = $this->db->fetchRow("
                SELECT DATE_FORMAT(login_time, '%d-%m-%Y %H:%i:%s') AS login_time, 
                       ip
				FROM core_session 
				WHERE user_id = ? 
				ORDER BY login_time DESC 
                LIMIT 1
            ", $show);

            if ($res) {
                echo '<div>' . $this->_('Последний раз заходил') . ' <b>' . $res['login_time'] . '</b> ' . $this->_('с IP адреса') . ' <b>' . $res['ip'] . '</b></div>';
            }

            $list = new \listTable($this->resId . 'xxx2');
            $list->addSearch($this->translate->tr("Время входа"), "login_time", "DATE");
            $list->addSearch("IP", "ip", "TEXT");
            //$list->addSearch("Отображать под", "r.boss", "text");

            $list->SQL = $this->db->quoteInto("
                SELECT user_id,
				       login_time,
					   COALESCE(logout_time, 'окончание сессии') AS _out,
					   COALESCE(ip, 'не определен') AS ip
                FROM `core_session` AS s
                WHERE user_id = ? /*ADD_SEARCH*/
                ORDER BY login_time DESC
			", $show);

            $list->addColumn($this->translate->tr("Время входа"), "220", "DATETIME");
            $list->addColumn($this->translate->tr("Время выхода"), "",   "TEXT");
            $list->addColumn("IP", "1%", "TEXT");

            //$list->editURL 			= $app . "&show=TCOL_00&tab_" . $this->resId . "=" . $tab->activeTab;
            $list->noCheckboxes = 'yes';
            $list->showTable();

        }
        else {
            $list = new \listTable($this->resId . 'xxx2');
            $list->addSearch($this->translate->tr("Пользователь"),               "u_login",       "TEXT");
            $list->addSearch($this->translate->tr("Время последней активности"), "last_activity", "DATE");
            $list->addSearch("IP",                         "ip",            "TEXT");

            $list->SQL = "
                SELECT u_id,
					   u.u_login,
					   (SELECT last_activity
                        FROM core_session
                        WHERE u.u_id = user_id
                        ORDER BY last_activity DESC
                        LIMIT 1) AS last_activity, 
					   
                       COALESCE((SELECT ip
                                 FROM core_session
                                 WHERE u.u_id = user_id
                                 ORDER BY last_activity DESC
                                 LIMIT 1), 'не определен') AS ip
				FROM core_users AS u
				WHERE 1=1 /*ADD_SEARCH*/
				ORDER BY last_activity DESC
            ";

            $list->addColumn($this->translate->tr("Пользователь"),               "",    "TEXT");
            $list->addColumn($this->translate->tr("Время последней активности"), "220", "DATETIME");
            $list->addColumn("IP",                                               "1",   "TEXT");

            $list->editURL 		= $app . "&show=TCOL_00&tab_" . $this->resId . "=2";
            $list->noCheckboxes = 'yes';
            $list->showTable();
        }
        return ob_get_clean();
    }

    public function getJournal()
    {
        $session = new SessionContainer('monitoring');
        $search = $session->search;
        $lines = $session->lines;

        $this->printCss("core2/mod/admin/assets/css/monitoring.css");
        $this->printJs("core2/mod/admin/assets/js/monitoring.js");

        ob_start();

        if (isset($this->config->log) &&
            isset($this->config->log->system) &&
            isset($this->config->log->system->writer) &&
            $this->config->log->system->writer == 'file'
        ) {
            if (!$this->config->log->system->file) {
                echo \Alert::getDanger($this->translate->tr('Не задан путь к файлу журнала'));
            } elseif (!file_exists($this->config->log->system->file)) {
                echo \Alert::getDanger($this->translate->tr('Отсутствует файл журнала'));
            } else {
                $data = $this->getLogsData('file', $search, $lines);
            }

            $count_lines = ! empty($data) ? $data['count_lines'] : '60';
            $body        = ! empty($data) ? $data['body']        : '';

        } else {
            $data        = $this->getLogsData('db', $search, $lines);
            $count_lines = $data['count_lines'];
            $body        = $data['body'];
        }

        $tpl = new \Templater3(__DIR__ . "/../../assets/html/monitoring.html");
        $tpl->assign('[COUNT_LINES]',  Tool::commafy($count_lines));
        $tpl->assign('[VIEW_LINES]',   $lines);
        $tpl->assign('[SEARCH]',       htmlspecialchars($search));
        $tpl->assign('[BODY]',         htmlspecialchars($body));

        echo $tpl->render();
        return ob_get_clean();
    }

    public function getArchive()
    {
        $zipFolder = $this->config->system->path_archive;
        if (empty($zipFolder)) {
            throw new \Exception("Не указана директория для архивов. Ее нужно указать в конфигурационном файле conf.ini с ключом 'system.path_archive'");

        } elseif ( ! is_dir($zipFolder)) {
            throw new \Exception("Директория не найдена. Ключ: system.path_archive = '$zipFolder'");
        }



        if (!is_writable($zipFolder)) {
            throw new \Exception("Директория '$zipFolder' защищена от записи.");
        }

        if (isset($_GET['edit'])) {

            $tempFile = $this->config->temp . "/test.txt";
            $zipFile = $zipFolder . "/" . date("d_m_YvH-i-s") . ".zip";

            $this->db->beginTransaction();
            try {

                /* Запись во временный файл */
                $f = fopen($tempFile, 'w');
                if (!$f) {
                    throw new \Exception($this->translate->tr("Ошибка записи во временный файл"));
                }
                $zip = new \ZipArchive();
                if ($zip->open($zipFile, \ZipArchive::CREATE) !== TRUE) {
                    throw new \Exception("Ошибка создания архива");
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

            } catch (\Exception $e) {
                $this->db->rollback();
                if (is_file($zipFile)) unlink($zipFile);
                echo $e->getMessage();
            }

        }



        $list = new \listTable($this->resId . 'archive');
        $list->noCheckboxes = "yes";
        $list->SQL = "SELECT 1";
        $list->addColumn($this->_("Имя файла"), "", "TEXT");
        $list->addColumn($this->_("Дата создания архива"), "", "DATETIME");
        $list->addColumn($this->_("Загрузить"), "90", "BLOCK");
        $data = $list->getData();

        $dir = opendir($zipFolder);
        if (!$dir) {
            throw new \Exception(sprintf($this->_("Не могу прочитать директорию '%s'. Проверьте права доступа."), $zipFolder));
        }
        $dataForList = array();
        $i = 0;

        while ($file = readdir($dir))
        {
            $i++;
            if ($file != "." && $file != ".." && !strpos($file, "svn"))

                if (!is_dir($zipFolder . "/" . $file))
                {

                    $file_create = stat($zipFolder."/".$file);
                    $dataForList[$i][] = $i;
                    $dataForList[$i][] = $file;
                    $dataForList[$i][] = date("Y-m-d H:i:s", filectime($zipFolder . "/" . $file));
                    $dataForList[$i][] = '<a href="index.php?module=admin&action=monitoring&tab_admin_monitoring=4&download='.$file.'"><img src="core2/html/'.THEME.'/img/templates_button.png" border="0"/></a>';

                }
        }

        closedir($dir);
        $list->data = $dataForList;
        // $list->classText['ADD'] = $this->translate->tr("Сформировать архив");
        // $list->addURL 			= $app . "&tab_admin_monitoring=4&edit=0";
        $list->showTable();
    }

    public function downloadJournal()
    {
        $session = new SessionContainer('monitoring');
        $search = $session->search;
        if ($this->config->log->system->writer == 'file') {
            if ($this->config->log->system->file && file_exists($this->config->log->system->file)) {
                $data = $this->getLogsData('file', $search);
                $body = $data['body'];
            } else {
                return \Alert::getDanger($this->translate->tr("Не задан путь к файлу журнала"));
            }
        } else {
            $data = $this->getLogsData('db', $search);
            $body = $data['body'];
        }
        return gzencode($body);

    }

    public function downloadArhive($fileName)
    {
        $zipFolder = $this->config->system->path_archive;
        $fileForDownload = $zipFolder . "/" . $fileName;

        $h = fopen($fileForDownload, 'rb');
        if (!$h) {
            throw new \Exception($this->translate->tr("Файл не найден!"));
        }
        $fs     = filesize($fileForDownload);
        $md5_sum = md5_file($fileForDownload);
        $fc     = fread($h, $fs);
        fclose($h);
        header("Content-Length: $fs");
        header("Content-md5: " . $md5_sum);
        header("Content-Disposition: attachment; filename=" . $fileName);
        ob_end_clean();
        return $fc;

    }


    /**
     * Получаем логи
     * @param string $type
     * @param string $search
     * @param int    $limit_lines
     * @return array
     */
    private function getLogsData($type, $search, $limit_lines = null) {

        if ($type == 'file') {
            $handle = fopen($this->config->log->system->file, "r");
            $count_lines = 0;
            while (!feof($handle)) {
                fgets($handle, 4096);
                $count_lines += 1;
            }

            if ($search) {
                $search = preg_quote($search, '/');
            }
            rewind($handle); //перемещаем указатель в начало файла
            $body = array();
            while (!feof($handle)) {
                $tmp = fgets($handle, 4096);
                if ($search) {
                    if (preg_match("/$search/", $tmp)) {
                        if (!$limit_lines || $limit_lines > count($body)) {
                            $body[] = $tmp;
                        } else {
                            array_shift($body);
                            $body[] = $tmp;
                        }
                    }
                } else {
                    if (!$limit_lines || $limit_lines >= count($body)) {
                        $body[] = $tmp;
                    } else {
                        array_shift($body);
                        $body[] = $tmp;
                    }
                }
            }
            fclose($handle);
            return array('body' => implode('', $body), 'count_lines' => $count_lines);

        } else {
            $where = '';
            if ($search) {
                $where = $this->db->quoteInto('WHERE u.u_login LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.sid LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.action LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.lastupdate LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.query LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.request_method LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.remote_port LIKE ?', "%$search%") .
                    $this->db->quoteInto(' OR l.ip LIKE ?', "%$search%");
            }
            $sql = "
                SELECT u.u_login,
                       l.sid,
                       l.action,
                       l.lastupdate,
                       l.query,
                       l.request_method,
                       l.remote_port,
                       l.ip
                FROM core_log AS l
                    LEFT JOIN core_users AS u ON u.u_id = l.user_id
                    $where
            ";

            if ($limit_lines) {
                $count_where = $this->db->fetchOne("
                    SELECT count(*)
                    FROM core_log AS l
                        LEFT JOIN core_users AS u ON u.u_id = l.user_id
                        $where
                ");

                $start = $count_where - $limit_lines;
                if ($start < 0) {
                    $start = 0;
                }
                $sql .= " LIMIT $start, $limit_lines ";
            }


            $data        = $this->db->fetchAll($sql);
            $count_lines = $this->db->fetchOne("SELECT count(*) FROM core_log");


            $data2 = '';
            foreach ($data as $tmp) {
                $data2 .= "user: {$tmp['u_login']}, sid: {$tmp['sid']}, action: {$tmp['action']}, lastupdate: {$tmp['lastupdate']}, query: {$tmp['query']}, query: {$tmp['request_method']}, remote_port: {$tmp['remote_port']}, ip: {$tmp['ip']}\n";
            }

            return array('body' => $data2, 'count_lines' => $count_lines);
        }
    }
}