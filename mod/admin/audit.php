<?

require_once __DIR__ . "/../admin/InstallModule.php";

$tab = new tabs('audit');

$tab->addTab($this->translate->tr("База данных"), 		    $app, 100);
$tab->addTab($this->translate->tr("Контроль целостности"),	$app, 150);

$tab->beginContainer("Аудит");

$pathToArray = "core2/mod/admin/audit/db_array.php";

if ($tab->activeTab == 1) {
    //$o_master = new DBMaster(); print_r($o_master->getSystemInstallDBArray());
    if (!file_exists($pathToArray)) {
        echo "Cannot find file";
        die;
    } else {
            require_once $pathToArray;
            $o_master = new DBMaster();
            $a_result = $o_master->checkCurrentDB($DB_ARRAY);
            $AuditNamespace = new Zend_Session_Namespace('Audit');
            //echo "<pre>";print_r($AuditNamespace->RES);die;
            //echo "<pre>";print_r($a_result);

        if (isset($_GET['db_update_one']) && $_GET['db_update_one'] == 1)
            if ($a_result['COM'] > 0 && is_array($AuditNamespace->RES)) {
                $a_tmp = explode('<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT-->', $AuditNamespace->RES['SQL'][$_GET['number']]);
                if ($a_tmp != ''){
                    $o_master->exeSQL($a_tmp[0]);

                }
                $a_result = $o_master->checkCurrentDB($DB_ARRAY);
            }

        $AuditNamespace->RES = $a_result;

        if (isset($_GET['db_update']) && $_GET['db_update'] == 1) {
            if ($a_result['COM'] > 0) {
                while (list($key, $val) = each($a_result['COM'])) {
                    $a_tmp = explode('<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT-->', $a_result['SQL'][$key]);
                    while (list($k, $v) = each($a_tmp)) {
                        if ($v != '') {
                            $o_master->exeSQL($v);
                        }
                    }
                }
            $a_result = $o_master->checkCurrentDB($DB_ARRAY);
            }
        }


        if (count($a_result['COM']) > 0) {
            reset($a_result['COM']);
            while (list($key, $val) = each($a_result['COM'])) {
                echo $val . '<span class="auditSql"><i>(' . $a_result['SQL'][$key] . ')</i></span>' . "&nbsp&nbsp<a href=\"javascript:load('?module=admin&action=audit&loc=core&db_update_one=1&number=".$key."')\"><b><span class=\"auditLineCorrect\">Исправить</span></b></a><br />";
            }
            echo "<input class=\"auditButton\" type=\"button\" value=\"Исправить все\" onclick=\"load('?module=admin&action=audit&loc=core&db_update=1')\"/>";
            echo "<h3>Предупреждения:</h3>";
            if ( ! empty($a_result['WARNING'])) {
                foreach ($a_result['WARNING'] as $val) {
                    echo "<span class=auditWarningText>".$val."</span></br>";
                }
            }
            die;
        }

        echo "Все ОК";
    }

}
elseif ($tab->activeTab == 2) {

    $install    = new InstallModule();

    try {
        $server = $this->config->system->host;
        $admin_email = $this->getSetting('admin_email');

        if (!$admin_email) {
            $id = $this->db->fetchOne("SELECT id FROM core_settings WHERE code = 'admin_email'");
            if (empty($id)) {
                $this->db->insert(
                    "core_settings",
                    array(
                        'system_name'   => 'Email для уведомлений от аудита системы',
                        'code'          => 'admin_email',
                        'is_custom_sw'  => 'Y',
                        'visible'       => 'Y'
                    )
                );
                $id = $this->db->lastInsertId("core_settings");
            }
            $install->addNotice("", "Создайте дополнительный параметр <a href=\"\" onclick=\"load('index.php#module=admin&action=settings&loc=core&edit={$id}&tab_settings=2'); return false;\">'admin_email'</a> с адресом для уведомлений", "Отправка уведомлений отключена", "info2");
        }
        if (!$server) {
            $install->addNotice("", "Не задан 'host' в conf.ini", "Отправка уведомлений отключена", "info2");
        }

        $is_puchkom = 1;
        $data = $this->db->fetchAll("SELECT module_id, m_name FROM core_modules WHERE is_system = 'N' AND files_hash IS NOT NULL");

        foreach ($data as $val) {
            $dirhash    = $install->extractHashForFiles($this->getModuleLocation($val['module_id']));
            $dbhash     = $install->getFilesHashFromDb($val['module_id']);
            $compare    = $install->compareFilesHash($dirhash, $dbhash);
            if (!empty($compare)) {
                $is_puchkom = 0;
                $val[2] = array();

                $br = $install->branchesCompareFilesHash($compare);
                foreach ($br as $type=>$branch) {
                    foreach ($branch as $n=>$f) {
                        if ($type != 'lost') {
                            $file = $this->getModuleLocation($val['module_id']) . "/" . $f;
                            $date = date("d.m.Y H:i:s", filemtime($file));
                            $br[$type][$n] = "{$file} (изменён {$date})";
                        }
                    }
                }

                $n = 0;
                if (!empty($br['added'])) {
                    $n += count($br['added']);
                    $val[2][]= "Добавленные файлы:<br>&nbsp;&nbsp; - " . implode("<br>&nbsp;&nbsp; - ", $br['added']);
                }
                if (!empty($br['changed'])) {
                    $n += count($br['changed']);
                    $val[2][]= "Измененные файлы:<br>&nbsp;&nbsp; - " . implode("<br>&nbsp;&nbsp; - ", $br['changed']);
                }
                if (!empty($br['lost'])) {
                    $n += count($br['lost']);
                    $val[2][]= "Удаленные файлы:<br>&nbsp;&nbsp; - " . implode("<br>&nbsp;&nbsp; - ", $br['lost']);
                }
                $val[2] = implode("<br><br>", $val[2]);
                echo "<div><h2>Обнаружены изменения в модуле \"{$val['m_name']}\"</h2>{$val[2]}</div><br><br>";
                //отправка уведомления

                if ($admin_email && $server) {
                    if ($this->isModuleActive('queue')) {
                        $is_send = $this->db->fetchOne(
                            "SELECT 1
                           FROM mod_queue_mails
                          WHERE subject = 'Обнаружены изменения в структуре модуля'
                            AND date_send IS NULL
                            AND DATE_FORMAT(date_add, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d')
                            AND body LIKE '%{$val['module_id']}%'"
                        );
                    } else {
                        $is_send = false;
                    }

                    if (!$is_send) {
                        $answer = $this->modAdmin->createEmail()
                            ->to($admin_email)
                            ->subject("{$server}: обнаружены изменения в структуре модуля!")
                            ->body("<b>{$server}:</b> Обнаружены изменения в структуре модуля {$val['module_id']}. Обнаружено  {$n} несоответствий.")
                            ->send();
                        if (isset($answer['error'])) {
                            $install->addNotice("", $answer['error'], "Уведомление не отправлено", "danger");
                        }
                    }
                }
            }
        }
        if ($is_puchkom) {
            echo "<h3>Всё пучком!</h3>";
        }

    } catch (Exception $e) {
        $install->addNotice("", $e->getMessage(), "Ошибка", "danger");
    }

    $html = $install->printNotices();
    if (!empty($html)) {
        echo "<hr>";
    }
    echo $html;

}
$tab->endContainer();

