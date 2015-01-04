<?

require_once("classes/Common.php");
require_once("classes/Templater.php");
require_once("classes/class.list.php");
require_once("classes/class.edit.php");
require_once("classes/class.tab.php");
require_once DOC_ROOT . 'core2/inc/classes/installModule.php';

/**
 * Class CoreController
 */
class CoreController extends Common {

	const RP = '8c1733d4cd0841199aa02ec9362be324';
	protected $tpl = '';
	protected $theme = 'default';
	
	public function __construct() {
		parent::__construct();
		$this->path = 'core2/mod/';
		$this->path .= !empty($this->module) ? $this->module . "/" : ''; 
		$this->tpl = new Templater();
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
	 * @return void
	 */
	public function action_index() {
        $install    = new InstallModule();

        $tab = new tabs('mod');
        $tab->beginContainer("События аудита");
        try {
            $changedMods = $this->checkModulesChanges($install);
            if (empty($changedMods)) {
                echo '<h3>Система работает в штатном режиме.</h3>';
            } else {
                echo '<h3 style="color: red;">Обнаружены изменения в файлах модулей: ' . implode(", ", $changedMods) . '</h3>';
            }
        } catch (Exception $e) {
            $install->addNotice("Аудит файлов модулей", $e->getMessage(), "Ошибка", "danger");
        }

        $html = $install->printNotices();
        if (!empty($html)) {
            echo "<hr>";
        }
        echo $html;
        $tab->endContainer();
	}


	/**
	 * @param $errorNamespace
	 * @param $blockNamespace
	 * @param $error
     * @return void
	 */
	private function setError ($errorNamespace, $blockNamespace, $error) {

		$errorNamespace->ERROR = $error;
		$this->processError($errorNamespace, $blockNamespace);
		Header("Location: index.php");
		die;
	}


	/**
	 * @param $errorNamespace
	 * @param $blockNamespace
	 */
	private function processError ($errorNamespace, $blockNamespace) {

		if (!empty($errorNamespace->ERROR)) {
			if (isset($errorNamespace->numberOfPageRequests)) {
        		$errorNamespace->numberOfPageRequests++;
    		} else {
		        $errorNamespace->numberOfPageRequests = 1;
		    }
		    if ($errorNamespace->numberOfPageRequests > 5) {
		    	
		    	$blockNamespace->blocked = time();
		    	$blockNamespace->setExpirationSeconds(10);
		    	
		    	$errorNamespace->numberOfPageRequests = 1;
		    }
		}
	}


    /**
     * @return void
     */
    public function action_login () {

		$errorNamespace = new Zend_Session_Namespace('Error');
		$blockNamespace = new Zend_Session_Namespace('Block');
		if (!empty($_POST['js_disabled'])) {
			$errorNamespace->ERROR = "Javascript выключен или ваш браузер его не поддерживает!";
			Header("Location: index.php");
			die;
		}
		$sign = '?';
		if (!empty($blockNamespace->blocked)) {
			$errorNamespace->ERROR = "Ваш доступ временно заблокирован!";
		} else {
			try {
				$db = Zend_Db::factory($this->config->database);
				$db->getConnection();
				//Zend_Db_Table::setDefaultAdapter($db);
			} catch (Exception $e) {
				$errorNamespace->ERROR = Error::catchLoginException($e);
				Header("Location: index.php");
				die;
			}
			$authLDAP = false;
			if ($_POST['login'] !== 'root') {
				//ldap
				if (!empty($this->config->ldap->active) && $this->config->ldap->active) {
					require_once 'core2/inc/classes/LdapAuth.php';
					$ldapAuth = new LdapAuth();
					$ldapAuth->auth($_POST['login'], $_POST['password']);
					$ldapStatus = $ldapAuth->getStatus();
					switch ($ldapStatus) {
						case LdapAuth::ST_LDAP_AUTH_SUCCESS :
							$authLDAP = true;
							$userData = $ldapAuth->getUserData();
							$login = $userData['login'];
							if (isset($userData['root']) && $userData['root'] === true) {
								$res = array();
								$res['u_pass'] 	= self::RP;
								$res['u_id'] 	= -1;
								$res['u_login'] = 'root';
								break;
							}

							$u_id  = $db->fetchOne("SELECT `u_id` FROM `core_users` WHERE `u_login` = ?", $login);
							if (!$u_id) {
								//create new user
								$dataForSave = array(					
									'visible' 		=> 'Y',
									'is_admin_sw' 	=> $userData['admin'] ? 'Y' : 'N',
									'u_login' 		=> $login,
									'date_added'	=> new Zend_Db_Expr('NOW()'),
								);
								$db->insert('core_users', $dataForSave);
							} elseif ($userData['admin']) {
								$db->update('core_users', array('is_admin_sw' => 'Y'), $db->quoteInto('u_id=?', $u_id));
							}
						break;
						
						case LdapAuth::ST_LDAP_USER_NOT_FOUND :							
//							$this->setError($errorNamespace, $blockNamespace, "Пользователь не найден");
							//удаляем пользователя если его нету в AD и с префиксом LDAP_%
							//$this->db->query("DELETE FROM core_users WHERE u_login = ?", $login);
							$login = $_POST['login'];
							$_POST['password'] = md5($_POST['password']);
						break;
						
						case LdapAuth::ST_LDAP_INVALID_PASSWORD :
							$this->setError($errorNamespace, $blockNamespace, "Неверный пароль или пользователь отключён");
						break;
						
						case LdapAuth::ST_ERROR :
							$this->setError($errorNamespace, $blockNamespace, "Ошибка LDAP: " . $ldapAuth->getMessage());
						break;
						
						default:
							$this->setError($errorNamespace, $blockNamespace, "Неизвестная ошибка авторизации по LDAP");
						break;
					}

				} else {
					$login = $_POST['login'];
				}

				if (empty($res)) {
					$res   = $db->fetchRow("SELECT `u_id`, `u_pass`, `u_login`, p.lastname, p.firstname, p.middlename, u.is_admin_sw, r.name AS role, u.role_id
								 FROM `core_users` AS u
								 	  LEFT JOIN core_users_profile AS p ON u.u_id = p.user_id
								 	  LEFT JOIN core_roles AS r ON r.id = u.role_id
								WHERE u.`visible` = 'Y' AND u.u_login=? LIMIT 1", $login);
				}
			} else {
				$res = array();
				$res['u_pass'] = self::RP;
				$res['u_id'] = -1;
				$res['u_login'] = 'root';
			}
			if ($res) {

				if ($authLDAP) {
					$res['LDAP'] = true;
				} else {
					$res['LDAP'] = false;
				}

				$md5_pass = Tool::pass_salt($_POST['password']);

				if ($res['LDAP']) {
					$res['u_pass'] = $md5_pass;
				}

				if ($res['u_pass'] !== $md5_pass) {
					$errorNamespace->ERROR = "Неверный пароль";
					$errorNamespace->TMPLOGIN = $res['u_login'];
					
					//$errorNamespace->setExpirationHops(1, 'ERROR');
				} else {

					$authNamespace = Zend_Registry::get('auth');
					$authNamespace->accept_answer 		= true;
					$sLife = $db->fetchOne("SELECT value FROM core_settings WHERE visible='Y' AND code='session_lifetime' LIMIT 1");
					if ($sLife) {
						$authNamespace->setExpirationSeconds($sLife, "accept_answer");
					}
					if (session_id() == 'deleted') {
						$errorNamespace->ERROR = "Ошибка сохранения сессии. Проверьте настройки системного времени.";
						$errorNamespace->TMPLOGIN = $res['u_login'];
					}
					$authNamespace->ID 		= (int) $res['u_id'];
					$authNamespace->NAME 	= $res['u_login'];
					if ($res['u_login'] == 'root') {
						$authNamespace->ADMIN = true;
					} else {
						$authNamespace->LN 		= $res['lastname'];
						$authNamespace->FN 		= $res['firstname'];
						$authNamespace->MN 		= $res['middlename'];
						$authNamespace->ADMIN 	= $res['is_admin_sw'] == 'Y' ? true : false;
						$authNamespace->ROLE 	= $res['role'] ? $res['role'] : -1;
						$authNamespace->ROLEID 	= $res['role_id'] ? $res['role_id'] : -1;
						$this->storeSession($authNamespace);
					}
					$authNamespace->LDAP = $res['LDAP'];
					$authNamespace->lock();
					$sign = '#';
				}
			} else {
				$errorNamespace->ERROR = "Нет такого пользователя";
				//$errorNamespace->setExpirationHops(1, 'ERROR');
			}			
			$this->processError($errorNamespace, $blockNamespace);
		}
		$url = "index.php";
		if (!empty($_SERVER['QUERY_STRING'])) {
			$url .= $sign . $_SERVER['QUERY_STRING'];
		}
		Header("Location: $url");
		die;
	}


	/**
     * @return void
	 */
	public function action_exit() {
		$this->closeSession();
		Zend_Session::destroy();
		Header("Location: index.php");
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
	 * @throws Exception
     * @return void
	 */
	public function action_switch(){
		try {
			if (!isset($_POST['data'])) {
				throw new Exception('Произошла ошибка! Не удалось получить данные');
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

		$app = "index.php?module=core&action=welcome&loc=core";

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
					throw new Exception('Выберите модуль.');
				}
				if (empty($supportFormMessage)) {
					throw new Exception('Введите текст сообщения.');
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
                        throw new Exception('Не удалось отправить сообщение');
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
		$tpl = new Templater();
		$tpl->loadTemplate("core2/mod/admin/html/feedback.tpl");
		$tpl->assign('</select>', $selectMods . '</select>');
		$this->printJs("core2/mod/admin/feedback.js", true);
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
		$out = '<div>Здравствуйте, ' . $name . '</div>';
		$sLife = $this->getSetting("session_lifetime");
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
			$out .= '<div>Последний раз Вы заходили <b>' . $res['login_time2'] . '</b> с IP адреса <b>' . $res['ip'] . '</b></div>';
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
		if ($this->isModuleActive('profile')) {
			$profile = $this->modProfile;
			if (method_exists($profile, 'getProfileMsg')) {
				$out .= $this->modProfile->getProfileMsg();
			}
		}
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
		$app = "index.php?module=admin&action=monitoring&loc=core";
		require_once $this->path . 'monitoring.php';
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
				if ($data['refid'] == 0) {		
					$dataForSave['u_login'] = $data['control']['u_login'];
					$dataForSave['date_added'] = new Zend_Db_Expr('NOW()');
					$this->db->insert('core_users', $dataForSave);						
					$last_insert_id = $this->db->lastInsertId(trim($data['table']));
				} else {
					$last_insert_id = $data['refid'];
					$where = $this->db->quoteInto('u_id = ?', $last_insert_id);
					$this->db->update('core_users', $dataForSave, $where);
				}				

				if ($last_insert_id) {
					$refid = $this->db->fetchOne("SELECT id FROM core_users_profile WHERE user_id=? LIMIT 1", $last_insert_id); 
					if (!$refid) {
						$this->db->insert('core_users_profile', array(
							'user_id' => $last_insert_id, 
							'lastname' => $lastname, 
							'firstname' => $firstname, 
							'middlename' => $middlename, 
							'lastuser' => $authNamespace->ID));
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

        $body  = "";
        $body .= "Уважаемый(ая) <b>{$dataNewUser['lastname']} {$dataNewUser['firstname']} {$dataNewUser['middlename']}</b>.<br/>";
        $body .= "Вы зарегистрированы на портале <a href=\"http://{$_SERVER["SERVER_NAME"]}\">{$_SERVER["SERVER_NAME"]}</a>.<br/>";
        $body .= "Ваш логин: '{$dataNewUser['u_login']}'.<br/>";
        $body .= "Ваш пароль: '{$dataNewUser['u_pass']}'.<br/>";
        $body .= "Зайти на портал можно по адресу <a href=\"http://{$_SERVER["SERVER_NAME"]}\">http://{$_SERVER["SERVER_NAME"]}</a>.";

        $result = $this->createEmail()
            ->from($dataUser['email'])
            ->to($dataUser['lastname'] . ' ' . $dataUser['firstname'])
            ->subject('Информация о регистрации на портале ' . $_SERVER["SERVER_NAME"])
            ->body($body)
            ->importance('HIGH')
            ->send();

  	    if (!$result) {
  	    	throw new Exception('Не удалось отправить сообщение пользователю');
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
            $install->addNotice("", "Создайте дополнительный параметр 'admin_email' с адресом для уведомлений", "Отправка уведомлений отключена", "info2");
        }
        if (!$server) {
            $install->addNotice("", "Не задан 'host' в conf.ini", "Отправка уведомлений отключена", "info2");
        }
        if (!$this->isModuleInstalled('queue')) {
            $install->addNotice("", "Установите модуль Очередь", "Отправка уведомлений отключена", "info2");
        } elseif (!$this->isModuleActive('queue')) {
            $install->addNotice("", "Включите модуль Очередь", "Отправка уведомлений отключена", "info2");
        }

        $data = $this->db->fetchAll("SELECT module_id FROM core_modules WHERE is_system = 'N' AND files_hash IS NOT NULL");
        $mods = array();
        foreach ($data as $val) {
            $dirhash    = $install->extractHashForFiles("mod/{$val['module_id']}");
            $dbhash     = $install->getFilesHashFromDb($val['module_id']);
            $compare    = $install->compareFilesHash($dirhash, $dbhash);
            if (!empty($compare)) {
//                $this->db->update("core_modules", array('visible' => 'N'), $this->db->quoteInto("module_id = ? ", $val['module_id']));
                $mods[] = $val['module_id'];
                //отправка уведомления
                if ($admin_email && $server && $this->isModuleActive('queue')) {
                    $is_send = $this->db->fetchOne(
                        "SELECT 1
                           FROM mod_queue_mails
                          WHERE subject = 'Обнаружены изменения в структуре модуля'
                            AND date_send IS NULL
                            AND DATE_FORMAT(date_add, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d')
                            AND body LIKE '%{$val['module_id']}%'"
                    );
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
                            ->from('informer@' . (substr_count($server, ".") > 0 ? $server : $server . '.com'))
                            ->subject('Обнаружены изменения в структуре модуля')
                            ->body("Обнаружены изменения в структуре модуля {$val['module_id']}. Обнаружено  {$n} несоответствий.")
                            ->send();
                        if (isset($answer['error'])) {
                            $install->addNotice("", $answer['error'], "Уведомление не отправлено", "danger");
                        }
                    }
                }
            }
        }

        return $mods;
    }



}