<?

require_once 'classes/Common.php';
require_once 'classes/class.list.php';
require_once 'classes/class.edit.php';
require_once 'classes/class.tab.php';
require_once 'classes/installModule.php';
require_once 'classes/Alert.php';

/**
 * Class CoreController
 */
class MobileController extends Common {

	protected $tpl = '';
	protected $theme = 'default';
	
	public function __construct() {
		parent::__construct();
		$this->module = 'admin';
		$this->path = 'core2/mod/';
		$this->path .= !empty($this->module) ? $this->module . "/" : '';
		if (!empty($this->config->theme)) {
			$this->theme = $this->config->theme;
		}
	}


    public function __call ($k, $arg) {
		if (!method_exists($this, $k)) return;
	}


	/**
	 * @param $var
	 * @param $value
	 */
	public function setVars($var, $value) {
		$this->$var = $value;
	}


    /**
     * @throws Exception
     * @return void
     */
	public function action_index() {
        if (!$this->auth->ADMIN) throw new Exception(911);

        $install    = new InstallModule();

        $tab = new tabs('mod');
        $tab->beginContainer($this->translate->tr("События аудита"));
        try {
            $changedMods = $this->checkModulesChanges($install);
            if (empty($changedMods)) {
                echo '<h3>' . $this->translate->tr("Система работает в штатном режиме.") . '</h3>';
            } else {
                echo '<h3 style="color:red;">' . $this->translate->tr("Обнаружены изменения в файлах модулей:") . ' ' . implode(", ", $changedMods) . '</h3>';
            }
        } catch (Exception $e) {
            $install->addNotice($this->translate->tr("Аудит файлов модулей"), $e->getMessage(), $this->translate->tr("Ошибка"), "danger");
        }

        $html = $install->printNotices();
        if (!empty($html)) {
            echo "<hr>";
        }
        echo $html;
        $tab->endContainer();
	}


	/**
	 * @throws Exception
     * @return void|string
	 */
	public function action_modules() {
        if (!$this->auth->ADMIN) throw new Exception(911);

		$app = "index.php?module={$this->module}&action=modules&loc=core";
		require_once $this->path . 'modules.php';
	}


	/**
     * Переключатель признака активности записи
	 * @throws Exception
     * @return void
	 */
	public function action_switch(){
		try {
			if (!isset($_POST['data'])) {
				throw new Exception($this->translate->tr('Произошла ошибка! Не удалось получить данные'));
			}

			$res = explode('.', $_POST['data']);

			preg_match('/[a-z|A-Z|0-9|_|-]+/', trim($res[0]), $arr);
			$table_name = $arr[0];
			$is_active = $res[1];
			$id = isset($res[2]) ? $res[2] : 0;
			if (!$id && !empty($_POST['value'])) {
				$id = (int) $_POST['value'];
			}
			$status = $_POST['is_active'];
			$keys_list = $this->db->fetchRow("SELECT * FROM `{$table_name}` LIMIT 1");
			$keys = array_keys($keys_list);
			$key = $keys[0];
			$where = $this->db->quoteInto($key . "= ?", $id);
			$this->db->update($table_name, array($is_active => $status), $where);
			//очистка кеша активности по всем записям таблицы
			// используется для core_modules
			$this->cache->clean(
					Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG,
					array("is_active_" . $table_name)
			);

			echo json_encode(array('status' => "ok"));
		} catch (Exception $e) {
			echo json_encode(array('status' => $e->getMessage()));
		}	
	}

    public function action_delete(Array $params)
    {
        $sess       = new Zend_Session_Namespace('List');
        $resource   = $params['res'];
        if (!$resource) throw new Exception($this->translate->tr("Не удалось определить идентификатор ресурса"), 13);
        if (!$params['id']) throw new Exception($this->translate->tr("Нет данных для удаления"), 13);
        $ids        = explode(",", $params['id']);
		$sessData = $sess->$resource;
        $deleteKey  = $sessData['deleteKey'];
        if (!$deleteKey) throw new Exception($this->translate->tr("Не удалось определить параметры удаления"), 13);
        list($table, $refid) = explode(".", $deleteKey);
        if (!$table || !$refid) throw new Exception($this->translate->tr("Не удалось определить параметры удаления"), 13);

        if (($this->checkAcl($resource, 'delete_all') || $this->checkAcl($resource, 'delete_owner'))) {
            $authorOnly = false;
            if ($this->checkAcl($resource, 'delete_owner') && !$this->checkAcl($resource, 'delete_all')) {
                $authorOnly = true;
            }
            $this->db->beginTransaction();
            try {
                $is = $this->db->fetchAll("EXPLAIN `$table`");

                $nodelete = false;
                $noauthor = true;

                foreach ($is as $value) {
                    if ($value['Field'] == 'is_deleted_sw') {
                        $nodelete = true;
                    }
                    if ($authorOnly && $value['Field'] == 'author') {
                        $noauthor = false;
                    }
                }
                if ($authorOnly) {
                    if ($noauthor) {
                        throw new Exception($this->translate->tr("Данные не содержат признака автора!"));
                    } else {
                        $auth = Zend_Registry::get('auth');
                    }
                }
                if ($nodelete) {
                    foreach ($ids as $key) {
                        $where = array($this->db->quoteInto("`$refid` = ?", $key));
                        if ($authorOnly) {
                            $where[] = $this->db->quoteInto("author = ?", $auth->NAME);
                        }
                        $this->db->update($table, array('is_deleted_sw' => 'Y'), $where);
                    }
                } else {
                    foreach ($ids as $key) {
                        $where = array($this->db->quoteInto("`$refid` = ?", $key));
                        if ($authorOnly) {
                            $where[] = $this->db->quoteInto("author = ?", $auth->NAME);
                        }
                        $this->db->delete($table, $where);
                    }
                }
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollback();
                throw new Exception($e->getMessage(), 13);
            }
        } else {
            throw new Exception(911, 13);
        }
        return true;
    }


	/**
	 * Обновление последовательности записей
	 */
	public function action_seq () {

		$this->db->beginTransaction();
		try {
			preg_match('/[a-z|A-Z|0-9|_|-]+/', trim($_POST['tbl']), $arr);
			$tbl = $arr[0];
			$res = $this->db->fetchPairs("SELECT id, seq FROM `$tbl` WHERE id IN ('" . implode("','", $_POST['data']) . "') ORDER BY seq ASC");
			if ($res) {
				$values = array_values($res);
				foreach ($_POST['data'] as $k => $val) {
					$where = $this->db->quoteInto('id=?', $val);
					$this->db->update($tbl, array('seq' => $values[$k]), $where);
				}
			}
			$this->db->commit();
			echo '{}';
		} catch (Exception $e) {
			$this->db->rollback();
			echo json_encode(array('error' => $e->getMessage()));
		}
		die;
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_users () {
		if (!$this->auth->ADMIN) throw new Exception(911);
		//require_once 'core2/mod/ModAjax.php';
		$app = "index.php?module={$this->module}&action=users";
		require_once $this->path . 'users.php';
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_settings () {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$app = "index.php?module=admin&action=settings&loc=core";
		require_once $this->path . 'settings.php';

	}


	/**
	 *
	 */
	public function action_welcome () {

		$app = "index.php?module=core&action=welcome";

		if (!empty($_POST['sendSupportForm'])) {
			if (isset($_POST['supportFormModule'])) {
				$supportFormModule = trim(strip_tags(stripslashes($_POST['supportFormModule'])));
			} else {
				$supportFormModule = '';
			}
			if (isset($_POST['supportFormMessage'])) {
				$supportFormMessage = trim(stripslashes($_POST['supportFormMessage']));
			} else {
				$supportFormMessage = '';
			}
			$supportFormMessagePost = $supportFormMessage;

			try {
				if (empty($supportFormModule)) {
					throw new Exception($this->translate->tr('Выберите модуль.'));
				}
				if (empty($supportFormMessage)) {
					throw new Exception($this->translate->tr('Введите текст сообщения.'));
				}

				$dataUser = $this->db->fetchRow("
                    SELECT lastname, firstname, middlename, cu.email, u_login
			   		FROM core_users as cu
			   		    LEFT JOIN core_users_profile AS cup ON cu.u_id = cup.user_id
			   		WHERE cu.u_id = ?", $this->auth->ID
				);
				if ($dataUser) {

					$to = $this->getSetting('feedback_email');
					$cc = $this->getSetting('feedback_email_cc');

					$supportFormMessage = "<pre>{$supportFormMessage}</pre>";
					$supportFormMessage .= '<hr/><small>';
					$supportFormMessage .= '<b>Хост:</b> ' . $_SERVER['HTTP_HOST'];
					$supportFormMessage .= '<br/><b>Модуль:</b> ' . $supportFormModule;
					$supportFormMessage .= '<br/><b>Пользователь:</b> ' . $dataUser['lastname'] . ' ' . $dataUser['firstname'] . ' ' . $dataUser['middlename'] . ' (Логин: ' . $dataUser['u_login'] . ')';
					$supportFormMessage .= '</small>';

                    $result = $this->createEmail()
                        ->from($dataUser['email'])
                        ->to($to)
                        ->cc($cc)
                        ->subject('Запрос обратной связи (модуль ' . $supportFormModule . ').')
                        ->body($supportFormMessage)
                        ->send();

                    if (isset($result['error'])) {
                        throw new Exception($this->translate->tr('Не удалось отправить сообщение'));
                    } else {
                        echo json_encode(array());
                    }
				}
			} catch (Exception $e) {
				echo json_encode(array('error' => array($e->getMessage())));
			}
			die;
		}
		if (file_exists('mod/home/welcome.php')) {
			require_once 'mod/home/welcome.php';
		}
	}


	/**
	 * Форма обратной связи
	 * @return mixed|string
	 */
	public function feedbackForm() {

		$mods = $this->db->fetchAll("SELECT m.module_id, m.m_name, sm.sm_key, sm.sm_name
							 FROM core_modules AS m
							 LEFT JOIN core_submodules AS sm ON sm.m_id = m.m_id AND sm.visible = 'Y'
							 WHERE m.visible = 'Y' ORDER BY sm.seq");

		$selectMods = '';
		if (count($mods)) {
			$currentMod = array();

			foreach ($mods as $key => $value) {
				if (!$value['module_id']) continue;
				if ($value['sm_key'] && !$this->checkAcl($value['module_id'] . '_' . $value['sm_key'], 'access')) {
					continue;
				} elseif (!$this->checkAcl($value['module_id'], 'access')) {
					continue;
				}
				if (!isset($currentMod[$value['m_name']])) {
					$currentMod[$value['m_name']] = array();
				}
				if ($value['sm_key']) {
					$currentMod[$value['m_name']][] = $value['sm_name'];
				}
			}

			foreach ($currentMod as $key => $value) {
				$selectMods .= '<option class="feedBackOption" value="' . $key . '">' . $key . '</option>';
				foreach ($value as $sub) {
					$valueSmMod = $key . '/' . $sub;
					$selectMods .= '<option value="' . $valueSmMod . '">&nbsp; &nbsp;' . $sub . '</option>';
				}
			}
		}
		$this->printJs("core2/mod/admin/feedback.js", true);
		require_once 'classes/Templater2.php';
		$tpl = new Templater2("core2/mod/admin/html/feedback.tpl");
		$tpl->assign('</select>', $selectMods . '</select>');
		return $tpl->parse();
	}


	/**
	 * @return string
	 */
	public function userProfile() {

		if ($this->auth->NAME !== 'root' && $this->auth->LDAP) {
			require_once 'core2/inc/classes/LdapAuth.php';
			$ldap = new LdapAuth();
			$ldap->getLdapInfo($this->auth->NAME);
		}

		$name = $this->auth->FN;
		if (!empty($name)) {
			$name .= ' ' . $this->auth->MN;
		} else {
			$name = $this->auth->NAME;
		}
		if (!empty($name)) $name = '<b>' . $name . '</b>';
		$out = '<div>' . sprintf($this->translate->tr("Здравствуйте, %s"), $name) . '</div>';
		$sLife = (int)$this->getSetting("session_lifetime");
		if (!$sLife) {
			$sLife = ini_get('session.gc_maxlifetime');
		}
		if ($this->config->database->adapter == 'Pdo_Mysql') {
			$res = $this->db->fetchRow("SELECT DATE_FORMAT(login_time, '%d-%m-%Y %H:%i') AS login_time2,
											   ip
										  FROM core_session
										 WHERE user_id = ?
										   AND (NOW() - last_activity > $sLife)=1
										 ORDER BY login_time DESC
										 LIMIT 1", $this->auth->ID);
		} elseif ($this->config->database->adapter == 'pdo_pgsql') {
			$res = $this->db->fetchRow("SELECT DATE_FORMAT(login_time, '%d-%m-%Y %H:%i') AS login_time2,
											   ip
										  FROM core_session
										 WHERE user_id = ?
										   AND EXTRACT(EPOCH FROM (NOW() - last_activity)) > $sLife
										 ORDER BY login_time DESC
										 LIMIT 1", $this->auth->ID);
		}
		if ($res) {
			$out .= '<div>' . sprintf($this->translate->tr("Последний раз Вы заходили %s с IP адреса %s"), '<b>' . $res['login_time2'] . '</b>', '<b>' . $res['ip'] . '</b>') . '</div>';
		}
		//Проверка активных сессий данного пользователя
		if ($this->config->database->adapter == 'Pdo_Mysql') {
			$res = $this->db->fetchAll("SELECT ip, sid
										  FROM core_session
										 WHERE user_id = ?
										   AND logout_time IS NULL
										   AND (NOW() - last_activity > $sLife)=0
										 ", $this->auth->ID);
		} elseif ($this->config->database->adapter == 'pdo_pgsql') {
			$res = $this->db->fetchAll("SELECT ip, sid
										  FROM core_session
										 WHERE user_id = ?
										   AND logout_time IS NULL
										   AND EXTRACT(EPOCH FROM (NOW() - last_activity)) <= $sLife
										 ", $this->auth->ID);
		}
		$co = count($res);
		if ($co > 1) {
			$ip = array();
			foreach ($res as $k => $value) {
				if ($value['sid'] == session_id()) continue;
				$ip[] = $value['ip'];
			}
			$ip = implode(', ', $ip);
			if ($co == 2) {
				$o = '';
				$px = 'ь';
				$px2 = 'й';
			} else {
				$o = 'o';
				$px2 = 'е';
				if (5 >= $co && $co > 2) $px = 'я';
				elseif ($co > 5) $px = 'ей';
			}
			$out .= '<div style="color:red"><b>Внимание! Обнаружен' . $o . ' еще ' . ($co - 1) . ' пользовател' . $px . ', использующи' . $px2 . ' вашу учетную запись.</b><br/>IP: ' . $ip . '</div>';
		}


		// Проверка наличия входящих непрочитаных сообщений
		$out .= $this->apiProfile->getProfileMsg();

		return $out;
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_roles() {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$app = "index.php?module=admin&action=roles&loc=core";
		$this->printCss($this->path . "role.css");
		require_once $this->path . 'roles.php';
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_enum() {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$this->printJs("core2/mod/admin/enum.js");
		$app = "index.php?module=admin&action=enum&loc=core";
		require_once $this->path . 'enum.php';
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_monitoring() {
		if (!$this->auth->ADMIN) throw new Exception(911);
        try {
            $app = "index.php?module=admin&action=monitoring&loc=core";
            require_once $this->path . 'monitoring.php';
        } catch (Exception $e) {
            Alert::printDanger($e->getMessage());
        }
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_audit() {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$app = "index.php?module=admin&action=audit&loc=core";
		require_once $this->path . 'DBMaster.php';
		require_once $this->path . 'audit.php';
	}


	/**
	 *
	 */
	public function action_upload() {
		require_once $this->path . 'upload.php';
	}


	/**
	 *
	 */
	public function action_handler() {
		require_once $this->path . 'file_handler.php';
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_saveUser() {

		if (!$this->auth->ADMIN) throw new Exception(911);
		if ($_POST['class_id'] == 'main_set') {
			$app = "index.php?module=admin&action=settings&loc=core";
			header("Location:$app");
		}
		if ($_POST['class_id'] == 'main_user') {
			$data = $_POST;
			$sess_form = new Zend_Session_Namespace('Form');
			$orderFields = $sess_form->main_user;

			$firstname = $data['control']['firstname'];
			$lastname = $data['control']['lastname'];
			$middlename = $data['control']['middlename'];
			$this->db->beginTransaction();
			
			try {
				$authNamespace = Zend_Registry::get('auth');
				$send_info_sw = false;
			  	if (!empty($data['control']['send_info_sw'][0]) && $data['control']['send_info_sw'][0] == 'Y') {
		     		$send_info_sw = true;
		     	} 
				$dataForSave = array(					
					'visible' 		=> $data['control']['visible'],
					'email' 		=> $data['control']['email'],
					'lastuser' 		=> $authNamespace->ID,
					'is_email_wrong' 	=> $data['control']['is_email_wrong'],
					'is_admin_sw' 	=> $data['control']['is_admin_sw'],
					'role_id' 		=> $data['control']['role_id']
				);
				if (isset($_FILES) && !empty($_FILES['control']['tmp_name']['certificate']) && $_FILES['control']['error']['certificate'] == 0) {
					$file = file_get_contents($_FILES['control']['tmp_name']['certificate']);
					if ($file) {
						$dataForSave['certificate'] = base64_encode($file);
					}					
				} elseif (!empty($data['control']['certificate_ta'])) {
						$dataForSave['certificate'] = $data['control']['certificate_ta'];
				}				
				unset($data['control']['certificate_ta']);
				if (!empty($data['control']['u_pass'])) {
					$dataForSave['u_pass'] = md5($data['control']['u_pass']);
				}
				if ($orderFields['refid'] == 0) {
					$dataForSave['u_login'] = $data['control']['u_login'];
					$dataForSave['date_added'] = new Zend_Db_Expr('NOW()');
					$this->db->insert('core_users', $dataForSave);						
					$last_insert_id = $this->db->lastInsertId('core_users');
				} else {
					$last_insert_id = $orderFields['refid'];
					$where = $this->db->quoteInto('u_id = ?', $last_insert_id);
					$this->db->update('core_users', $dataForSave, $where);
				}				

				if ($last_insert_id) {
					$refid = $this->db->fetchOne("SELECT id FROM core_users_profile WHERE user_id=? LIMIT 1", $last_insert_id); 
					if (!$refid) {
						$this->db->insert('core_users_profile', array(
                                'user_id'    => $last_insert_id,
                                'lastname'   => $lastname,
                                'firstname'  => $firstname,
                                'middlename' => $middlename,
                                'lastuser'   => $authNamespace->ID)
                        );
					} else {						
						$where = $this->db->quoteInto('user_id = ?', $last_insert_id);
						$this->db->update('core_users_profile', 
										array('lastname' => $lastname, 
												'firstname' => $firstname, 
												'middlename' => $middlename, 
												'lastuser' => $authNamespace->ID),
										$where);
					}
				}
				if ($send_info_sw) {
					$this->sendUserInformation($data['control']);
				}
				$this->db->commit();				
	     	} catch (Exception $e) {
	     		$this->db->rollback();				
				$errorNamespace = new Zend_Session_Namespace('Error');
				$errorNamespace->ERROR =  $e->getMessage();				
				$errorNamespace->setExpirationHops(1);
			}			
			header("Location:{$_POST['back']}");			
		}
	}


	/**
	 * @param $dataNewUser
	 * @throws Exception
     * @return void
	 */
	private function sendUserInformation($dataNewUser) {

		$dataUser = $this->db->fetchRow("SELECT lastname, firstname, middlename
			   							 FROM core_users AS cu
			   							 LEFT JOIN core_users_profile AS cup ON cu.u_id = cup.user_id
			   							 WHERE cu.u_id = ?",
			$this->auth->ID
		);

        $body = "Уважаемый(ая) <b>{$dataNewUser['lastname']} {$dataNewUser['firstname']} {$dataNewUser['middlename']}</b>.<br/>";
        $body .= "Вы зарегистрированы на портале <a href=\"http://{$_SERVER["SERVER_NAME"]}\">{$_SERVER["SERVER_NAME"]}</a>.<br/>";
        $body .= "Ваш логин: '{$dataNewUser['u_login']}'.<br/>";
        $body .= "Ваш пароль: '{$dataNewUser['u_pass']}'.<br/>";
        $body .= "Зайти на портал можно по адресу <a href=\"http://{$_SERVER["SERVER_NAME"]}\">http://{$_SERVER["SERVER_NAME"]}</a>.";

        $result = $this->createEmail()
            ->from($dataUser['email'])
            ->to($dataUser['lastname'] . ' ' . $dataUser['firstname'])
            ->subject(sprintf($this->translate->tr("Информация о регистрации на портале %s"), $_SERVER["SERVER_NAME"]))
            ->body($body)
            ->importance('HIGH')
            ->send();

  	    if (!$result) {
  	    	throw new Exception($this->translate->tr('Не удалось отправить сообщение пользователю'));
  	    }
	}


    /**
     * Создание письма
     * @return Email
     */
    public function createEmail() {

        require_once 'classes/Email.php';
        return new Email();
    }


    /**
     * Проверяем файлы модулей на изменения
     *
     * @param $install
     *
     * @return array
     */
    public function checkModulesChanges($install) {
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
            $install->addNotice("", $this->translate->tr("Не задан параметр 'host' в conf.ini"), $this->translate->tr("Отправка уведомлений отключена"), "info2");
        }

        $data = $this->db->fetchAll("SELECT module_id FROM core_modules WHERE is_system = 'N' AND files_hash IS NOT NULL");
        $mods = array();
        foreach ($data as $val) {
            $dirhash    = $install->extractHashForFiles($this->getModuleLocation($val['module_id']));
            $dbhash     = $install->getFilesHashFromDb($val['module_id']);
            $compare    = $install->compareFilesHash($dirhash, $dbhash);
            if (!empty($compare)) {
//                $this->db->update("core_modules", array('visible' => 'N'), $this->db->quoteInto("module_id = ? ", $val['module_id']));
                $mods[] = $val['module_id'];
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
                        $n = 0;
                        $br = $install->branchesCompareFilesHash($compare);
                        if (!empty($br['added'])) {
                            $n += count($br['added']);
                        }
                        if (!empty($br['changed'])) {
                            $n += count($br['changed']);
                        }
                        if (!empty($br['lost'])) {
                            $n += count($br['lost']);
                        }
                        $answer = $this->modAdmin->createEmail()
                            ->to($admin_email)
                            ->subject("{$server}: обнаружены изменения в структуре модуля")
                            ->body("<b>{$server}:</b> обнаружены изменения в структуре модуля {$val['module_id']}. Обнаружено  {$n} несоответствий.")
                            ->send();
                        if (isset($answer['error'])) {
                            $install->addNotice("", $answer['error'], $this->translate->tr("Уведомление не отправлено"), "danger");
                        }
                    }
                }
            }
        }

        return $mods;
    }



}