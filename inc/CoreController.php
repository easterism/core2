<?

require_once 'classes/Common.php';
require_once 'classes/class.list.php';
require_once 'classes/class.edit.php';
require_once 'classes/class.tab.php';
require_once 'classes/Alert.php';

/**
 * Class CoreController
 */
class CoreController extends Common {

	const RP = 'e3fb5f90f7bcb59fd5b9dbc030335c07';
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

        $tab = new tabs('mod');
        $tab->beginContainer($this->translate->tr("События аудита"));
        try {
            $changedMods = $this->checkModulesChanges();
            if (empty($changedMods)) {
                Alert::memory()->info($this->translate->tr("Система работает в штатном режиме."));
            } else {
				Alert::memory()->danger(implode(", ", $changedMods), $this->translate->tr("Обнаружены изменения в файлах модулей:"));
            }
            if (!$this->moduleConfig->database || !$this->moduleConfig->database->admin || !$this->moduleConfig->database->admin->username) {
				Alert::memory()->warning("Задайте параметр 'database.admin.username' в conf.ini модуля 'admin'", $this->translate->tr("Не задан администратор базы данных"));
            }
        } catch (Exception $e) {
			Alert::memory()->danger($this->translate->tr("Ошибка"), $e->getMessage());
        }

        echo Alert::get();
        $tab->endContainer();
	}


	/**
     * Используется при входе в систему
	 * @param $errorNamespace
	 * @param $blockNamespace
	 * @param $error
     * @return void
	 */
	private function setError ($errorNamespace, $blockNamespace, $error) {

		$errorNamespace->ERROR = $error;
		$this->processError($errorNamespace, $blockNamespace);
        header("HTTP/1.1 400 Bad Request");
        header("Location: index.php");
		die;
	}


	/**
     * Используется при входе в систему
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
		    	$blockNamespace->setExpirationSeconds(60);
		    	
		    	$errorNamespace->numberOfPageRequests = 1;
		    }
		}
	}


    /**
	 * Авторизация пользователя через форму
	 *
     * @return void
     */
    public function action_login ($post) {

		$errorNamespace = new Zend_Session_Namespace('Error');
		$blockNamespace = new Zend_Session_Namespace('Block');
		$tokenNamespace = new Zend_Session_Namespace('Token');
		if (!empty($post['js_disabled'])) {
			$errorNamespace->ERROR = $this->catchLoginException(new Exception($this->translate->tr("Javascript выключен или ваш браузер его не поддерживает!"), 400));
            header("Location: index.php");
			return;
		}
		$sign = '?';
		if (!empty($blockNamespace->blocked)) {
			$errorNamespace->ERROR = $this->translate->tr("Ваш доступ временно заблокирован!");
		}
		else {
			if (empty($tokenNamespace->TOKEN) || $tokenNamespace->TOKEN !== $post['action']) {
				$errorNamespace->ERROR = $this->catchLoginException(new Exception($this->translate->tr("Ошибка авторизации!")));
                header("Location: index.php");
				return;
			}
			try {
				$db = Zend_Db::factory($this->config->database);
				$db->getConnection();
			} catch (Exception $e) {
				$errorNamespace->ERROR = $this->catchLoginException($e);
                header("Location: index.php");
				return;
			}
			$authLDAP = false;
            $login = trim($post['login']);
            $passw = $post['password'];

            if (!ctype_print($passw)) {
                $errorNamespace->ERROR = $this->catchLoginException(new Exception($this->translate->tr("Ошибка пароля!")));
                header("Location: index.php");
                return;
            }
			if ($login !== 'root') {
				//ldap
				if (!empty($this->config->ldap->active) && $this->config->ldap->active) {
					require_once 'core2/inc/classes/LdapAuth.php';
                    $ldapAuth = new LdapAuth();
                    $ldapAuth->auth($login, $passw);
                    $ldapStatus = $ldapAuth->getStatus();
					switch ($ldapStatus) {
						case LdapAuth::ST_LDAP_AUTH_SUCCESS :
                            $authLDAP = true;
                            $userData = $ldapAuth->getUserData();
                            $login    = $userData['login'];
							if (isset($userData['root']) && $userData['root'] === true) {
                                $res = $this->setRoot();
								break;
							}

                            $u_id = $this->dataUsers->fetchRow($this->db->quoteInto("u_login = ?", $login))->u_id;
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
                            $passw = md5($passw);
						break;
						
						case LdapAuth::ST_LDAP_INVALID_PASSWORD :
							$this->setError($errorNamespace, $blockNamespace, $this->translate->tr("Неверный пароль или пользователь отключён"));
						break;
						
						case LdapAuth::ST_ERROR :
							$this->setError($errorNamespace, $blockNamespace, $this->translate->tr("Ошибка LDAP: ") . $ldapAuth->getMessage());
						break;
						
						default:
							$this->setError($errorNamespace, $blockNamespace, $this->translate->tr("Неизвестная ошибка авторизации по LDAP"));
						break;
					}
				}

				if (empty($res)) {
					$res   = $db->fetchRow("SELECT `u_id`, `u_pass`, u.email, `u_login`, p.lastname, p.firstname, p.middlename, u.is_admin_sw, r.name AS role, u.role_id
								 FROM `core_users` AS u
								 	  LEFT JOIN core_users_profile AS p ON u.u_id = p.user_id
								 	  LEFT JOIN core_roles AS r ON r.id = u.role_id
								WHERE u.`visible` = 'Y' AND u.u_login=? LIMIT 1", $login);
				}
			}
			else {
				$res = $this->setRoot();
			}
			if ($res) {

				if ($authLDAP) {
					$res['LDAP'] = true;
				} else {
					$res['LDAP'] = false;
				}

				$md5_pass = Tool::pass_salt($passw);

				if ($res['LDAP']) {
					$res['u_pass'] = $md5_pass;
				}

				if ($res['u_pass'] !== $md5_pass) {
					$errorNamespace->ERROR = $this->translate->tr("Неверный пароль");
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
						$errorNamespace->ERROR = $this->translate->tr("Ошибка сохранения сессии. Проверьте настройки системного времени.");
						$errorNamespace->TMPLOGIN = $res['u_login'];
					}
					$authNamespace->ID 		= (int) $res['u_id'];
					$authNamespace->NAME 	= $res['u_login'];
					$authNamespace->EMAIL 	= $res['email'];
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
				$errorNamespace->ERROR = $this->translate->tr("Нет такого пользователя");
				//$errorNamespace->setExpirationHops(1, 'ERROR');
			}			
			$this->processError($errorNamespace, $blockNamespace);
		}
		$url = "index.php";
		if (!empty($_SERVER['QUERY_STRING'])) {
			$url .= $sign . $_SERVER['QUERY_STRING'];
		}
        header("Location: $url");
		return;
	}

    /**
     * Обработка исключений входа в систему
     * @param $exception
     * @return mixed
     */
    private function catchLoginException($exception)
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();
        if ($code == 400) {
            header("HTTP/1.1 400 Bad Request");
        } else {
            header("HTTP/1.1 503 Service Unavailable");
        }
        if ($code == 1044) {
            return $this->translate->tr('Нет доступа к базе данных.');
        } elseif ($code == 2002) {
            return $this->translate->tr('Не верный адрес базы данных.');
        } elseif ($code == 1049) {
            return $this->translate->tr('Нет соединения с базой данных.');
        } else {
            return $message;
        }
    }

    /**
     * установка данных дя пользователя root
     * @return array
     */
    private final function setRoot() {
        $res            = array();
        $res['u_pass']  = self::RP;
        $res['u_id']    = -1;
        $res['u_login'] = 'root';
        $res['email']   = 'easter.by@gmail.com';
        return $res;
    }

	/**
     * @return void
	 */
	public function action_exit() {
		$this->closeSession();
		Zend_Session::destroy();
        return;
	}


	/**
	 * @throws Exception
     * @return void|string
	 */
	public function action_modules() {
        if (!$this->auth->ADMIN) throw new Exception(911);

		$app = "index.php?module={$this->module}&action=modules";
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

	/**
	 * Занимается удалением записей в таблицах базы данных
	 * если в талице есть поле is_deleted_sw, то запись не удаляется, а поле is_deleted_sw принимает значение 'Y'
	 *
	 * @param array $params
	 *
	 * @return bool
	 * @throws Exception
	 */
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
		$app = "index.php?module=admin&action=settings";
		require_once $this->path . 'settings.php';

	}


	/**
	 *
	 */
	public function action_welcome () {

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

			header('Content-type: application/json; charset="utf-8"');

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
                    }
				}
				echo '{}';
			} catch (Exception $e) {
                \Core2\Error::catchJsonException(array('error' => array($e->getMessage())), 503);
			}
			return;
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

		$mods = $this->db->fetchAll("
			SELECT m.module_id,
				   m.m_name,
				   sm.sm_key,
				   sm.sm_name
			FROM core_modules AS m
			    LEFT JOIN core_submodules AS sm ON sm.m_id = m.m_id AND sm.visible = 'Y'
			WHERE m.visible = 'Y'
			ORDER BY sm.seq
        ");

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

                $value['m_name']  = strip_tags($value['m_name']);
                $value['sm_name'] = strip_tags($value['sm_name']);

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
	 * информация о профиле пользователя
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
		$app = "index.php?module=admin&action=roles";
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
		$app = "index.php?module=admin&action=enum";
		require_once $this->path . 'enum.php';
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_monitoring() {
		if (!$this->auth->ADMIN) throw new Exception(911);
        try {
            $app = "index.php?module=admin&action=monitoring";
            require_once $this->path . 'monitoring.php';
        } catch (Exception $e) {
            echo Alert::danger($e->getMessage());
        }
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_audit() {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$app = "index.php?module=admin&action=audit";
		require_once $this->path . 'audit/Audit.php';
        $audit = new \Core2\Audit();
        $tab = new tabs('audit');

        $tab->addTab($this->translate->tr("База данных"), 		    $app, 100);
        $tab->addTab($this->translate->tr("Контроль целостности"),	$app, 150);

        $tab->beginContainer("Аудит");

        if ($tab->activeTab == 1) {
            $audit->database();
        }
        elseif ($tab->activeTab == 2) {
            $audit->integrity();
        }
        $tab->endContainer();
	}


	/**
	 *
	 */
	public function action_upload() {
		require_once $this->path . 'upload.php';
	}


	/**
	 * обработка запросов на содержимое файлов
	 */
	public function fileHandler($resource, $context, $table, $id) {
		require_once 'classes/File.php';
		$f = new \Store\File();
        $f->setResource($resource);
		if ($context == 'fileid') {
			$f->handleFile($table, $id);
		}
		elseif ($context == 'thumbid') {
			$f->handleThumb($table, $id);
		}
		elseif ($context == 'tfile') {
			$f->handleFileTemp($id);
		}
		elseif (substr($context, 0, 6) == 'field_') {
            header('Content-type: application/json');
			$f->handleFileList($table, $id, substr($context, 6));
			return true;
		}
		$f->dispatch();
        return true;
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_saveUser() {

		if (!$this->auth->ADMIN) throw new Exception(911);
		if ($_POST['class_id'] == 'main_set') {
			$app = "index.php?module=admin&action=settings";
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

		$dataUser = $this->db->fetchRow("SELECT lastname, firstname, middlename, email
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
     * @return array
     */
    private function checkModulesChanges() {
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
            Alert::memory()->info("Создайте дополнительный параметр <a href=\"\" onclick=\"load('index.php#module=admin&action=settings&edit={$id}&tab_settings=2'); return false;\">'admin_email'</a> с адресом для уведомлений", $this->translate->tr("Отправка уведомлений отключена"));
        }
        if (!$server) {
            Alert::memory()->info($this->translate->tr("Не задан параметр 'host' в conf.ini"), $this->translate->tr("Отправка уведомлений отключена"));
        }

        $data = $this->db->fetchAll("SELECT module_id FROM core_modules WHERE is_system = 'N' AND files_hash IS NOT NULL");
        $mods = array();

        require_once DOC_ROOT . "core2/mod/admin/InstallModule.php";
        $install    = new \Core2\InstallModule();

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
                            Alert::memory()->danger($answer['error'], $this->translate->tr("Уведомление не отправлено"));
                        }
                    }
                }
            }
        }

        return $mods;
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