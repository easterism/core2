<?

require_once 'Zend/Db/Table.php';
require_once 'Zend/Registry.php';
require_once 'Zend/Cache.php';


/**
 * Class Db
 */
class Db {
	protected $config;
	protected $frontendOptions = array(
		'lifetime'                => 40000,
		'automatic_serialization' => true
	);
	protected $backendOptions = array();
	protected $backend = 'File';
	private $_s        = array();
	private $_settings = array();


	/**
	 * Db constructor.
	 * @param null $config
	 */
	public function __construct($config = null) {
		if (is_null($config)) {
			$this->config = Zend_Registry::get('config');
		} else {
			$this->config = $config;
		}
		if (!$config) return false;

	}


	/**
	 * @param string $k
	 * @return mixed|Zend_Cache_Core|Zend_Cache_Frontend|Zend_Db_Adapter_Abstract|\Core2\Log
	 * @throws Zend_Exception
	 * @throws Exception
	 */
	public function __get($k) {
		if ($k == 'db') {
			$reg = Zend_Registry::getInstance();
			if (!$reg->isRegistered('db')) {
				if (!$this->config) $this->config = $reg->get('config');
				$db = $this->establishConnection($this->config->database);
			} else {
				$db = $reg->get('db');
			}
			return $db;
		}
		// Получение указанного кэша
		if ($k == 'cache') {
			$reg = Zend_Registry::getInstance();
			if (!$reg->isRegistered($k)) {
				$v = Zend_Cache::factory('Core',
					$this->backend,
					$this->frontendOptions,
					array('cache_dir' => $this->config->cache));
				$reg->set($k, $v);
			} else {
				$v = $reg->get($k);
			}
			return $v;
		}
		// Получение экземпляра переводчика
		if ($k == 'translate') {
			if (array_key_exists($k, $this->_s)) {
				$v = $this->_s[$k];
			} else {
				$v = Zend_Registry::get('translate');
				$this->_s[$k] = $v;
			}
			return $v;
		}
		// Получение экземпляра логера
		elseif ($k == 'log') {
			if (array_key_exists($k, $this->_s)) {
				$v = $this->_s[$k];
			} else {
				$v = new \Core2\Log();
				$this->_s[$k] = $v;
			}
			return $v;
		}
		// Получение экземпляра модели текущего модуля
		elseif (strpos($k, 'data') === 0) {
			if (array_key_exists($k, $this->_s)) {
				$v = $this->_s[$k];
			} else {
				$this->db; ////FIXME грязный хак для того чтобы сработал сеттер базы данных. Потому что иногда его здесь еще нет, а для инициализаци модели используется адаптер базы данных по умолчанию
				$module   = explode("|", $k);
				$model    = substr($module[0], 4);
				$module   = !empty($module[1]) ? $module[1] : 'admin';
				$location = $module == 'admin'
					? DOC_ROOT . "core2/mod/admin"
					: $this->getModuleLocation($this->module);

				if (!file_exists($location . "/Model/$model.php")) throw new Exception($this->traslate->tr('Модель не найдена.'));
				require_once($location . "/Model/$model.php");
				$v            = new $model();
				$this->_s[$k] = $v;
			}
			return $v;
		}
	}

    /**
     * @param Zend_Config $database
     * @return Zend_Db_Adapter_Abstract
     */
    protected function establishConnection(Zend_Config $database) {
		try {
			$db = Zend_Db::factory($database);
			Zend_Db_Table::setDefaultAdapter($db);
			$db->getConnection();
			Zend_Registry::getInstance()->set('db', $db);
			if ($this->config->system->timezone) $db->query("SET time_zone = '{$this->config->system->timezone}'");
            return $db;
        } catch (Zend_Db_Adapter_Exception $e) {
            \Core2\Error::catchDbException($e);
        } catch (Zend_Exception $e) {
            \Core2\Error::catchZendException($e);
        }
	}


	/**
	 * Установка соединения с произвольной базой MySQL
	 * @param string $dbname
	 * @param string $username
	 * @param string $password
	 * @param string $host
	 * @param string $charset
	 * @param string $adapter
	 *
	 * @return Zend_Db_Adapter_Abstract
	 */
	public function newConnector($dbname, $username, $password, $host = 'localhost', $charset = 'utf8', $adapter = 'Pdo_Mysql') {
	    $host = explode(":", $host);
		$temp = array(
			'host'     => $host[0],
			'port'     => isset($host[1]) ? $host[1] : 3306,
			'username' => $username,
			'password' => $password,
			'dbname'   => $dbname,
			'charset'  => $charset,
            'adapterNamespace' => 'Core_Db_Adapter'
		);
		try {
			$db = Zend_Db::factory($adapter, $temp);
			$db->getConnection();
            return $db;
        } catch (Zend_Db_Adapter_Exception $e) {
            \Core2\Error::catchDbException($e);
        } catch (Zend_Exception $e) {
            \Core2\Error::catchZendException($e);
        }
	}


	/**
	 * @param string $resId
	 * @return array
	 */
	public function getModuleName($resId) {
		if ( ! ($this->cache->test($resId . '_name'))) {
			$data = explode("_", $resId);
			if ( ! empty($data[1])) {
				$res = $this->db->fetchRow("
                    SELECT m.m_name,
                           sm.sm_name
                    FROM core_modules AS m
                        INNER JOIN core_submodules AS sm ON sm.m_id = m.m_id
                    WHERE CONCAT(m.module_id, '_', sm.sm_key) = ?
                ", $resId);
				$res = array($res['m_name'], $res['sm_name']);
			} else {
				$res = $this->db->fetchRow("
                    SELECT m.m_name
                    FROM core_modules AS m
                    WHERE m.module_id = ?
                ", $resId);
				$res = array($res['m_name']);
			}
			$this->cache->save($res, $resId . '_name');
		} else {
			$res = $this->cache->load($resId . '_name');
		}
		return $res;
	}


	/**
	 * Сохранение информации о входе пользователя
	 * @param Zend_Session_Namespace $auth
	 */
	protected function storeSession(Zend_Session_Namespace $auth) {
		if ($auth && $auth->ID && $auth->ID > 0) {
			$sid = Zend_Session::getId();
			$s_id = $this->db->fetchOne("SELECT id FROM core_session WHERE logout_time IS NULL AND user_id=? AND sid=? AND ip=? LIMIT 1", array($sid, $auth->ID, $_SERVER['REMOTE_ADDR']));
			if (!$s_id) {
				$this->db->insert('core_session', array(
						'sid' => $sid,
						'login_time' => new Zend_Db_Expr('NOW()'),
						'user_id' => $auth->ID,
						'ip' => $_SERVER['REMOTE_ADDR']
					)
				);
			}
		}
	}


	/**
	 * @param string $expired
	 */
	public function closeSession($expired = 'N') {
		$auth = Zend_Registry::get('auth');
		if ($auth && $auth->ID && $auth->ID > 0) {
			$where = $this->db->quoteInto("user_id = ?", $auth->ID);
			$where2 = $this->db->quoteInto("sid=?", Zend_Session::getId());
			$where3 = $this->db->quoteInto("ip=?", $_SERVER['REMOTE_ADDR']);
			$this->db->update('core_session', array(
				'logout_time' => new Zend_Db_Expr('NOW()'),
				'is_expired_sw' => $expired),
				array($where, $where2, $where3)
			);
		}
	}


	/**
	 * логирование активности простых пользователей
	 * @param array $exclude исключения адресов
	 * @throws Exception
	 */
	public function logActivity($exclude = array()) {
		$auth = Zend_Registry::get('auth');
		if ($auth->ID && $auth->ID > 0) {
			if ($exclude) {
				if (in_array($_SERVER['QUERY_STRING'], $exclude)) return;
			}
			$arr = array();
			if (!empty($_POST)) $arr['POST'] = $_POST;
			if (!empty($_GET)) $arr['GET'] = $_GET;
			$data = array(
				'ip' 			=> $_SERVER['REMOTE_ADDR'],
				'sid' 			=> Zend_Session::getId(),
				'request_method' => $_SERVER['REQUEST_METHOD'],
				'remote_port' 	=> $_SERVER['REMOTE_PORT'],
				'query' 		=> $_SERVER['QUERY_STRING'],
				'user_id' 		=> $auth->ID
			);
			if ($arr) {
				$data['action'] = serialize($arr);
			}
			if (isset($this->config->log) && $this->config->log &&
				isset($this->config->log->system->writer) && $this->config->log->system->writer == 'file'
			) {
				if (!$this->config->log->system->file) {
					throw new Exception($this->traslate->tr('Не задан файл журнала запросов'));
				}

				$log = new \Core2\Log('access');
				$log->access($auth->NAME);

			} else {
				$this->db->insert('core_log', $data);
			}
			// обновление записи о последней активности
			$where = array($this->db->quoteInto("sid=?", $data['sid']),
						   $this->db->quoteInto("ip=?", $data['ip']),
						   $this->db->quoteInto("user_id=?", $data['user_id']),
						   'logout_time IS NULL'
			);
			$this->db->update('core_session', array('last_activity' => new Zend_Db_Expr('NOW()')), $where);
		}
	}


	/**
	 * @param string $code
	 * @return string
	 */
	public function getSetting($code) {
		$this->getAllSettings();
		return isset($this->_settings[$code]) ? $this->_settings[$code]['value'] : false;
	}


	/**
	 * @param string $code
	 * @return string
	 */
	public function getCustomSetting($code) {
		$this->getAllSettings();
		if (isset($this->_settings[$code]) && $this->_settings[$code]['is_custom_sw'] == 'Y') {
			return $this->_settings[$code]['value'];
		}
		return false;
	}


	/**
	 * @param string $code
	 * @return string
	 */
	public function getPersonalSetting($code) {
		$this->getAllSettings();
		if (isset($this->_settings[$code]) && $this->_settings[$code]['is_personal_sw'] == 'Y') {
			return $this->_settings[$code]['value'];
		}
		return false;
	}


	/**
	 * @param string $global_id
	 * @return array
	 */
	public function getEnumList($global_id) {
		$res = $this->db->fetchAll("SELECT id, name, custom_field, is_default_sw
									FROM core_enum
									WHERE is_active_sw = 'Y'
									AND parent_id = (SELECT id FROM core_enum WHERE global_id=? AND is_active_sw='Y')
									ORDER BY seq", $global_id);
		$data = array();
		foreach ($res as $value) {
			$data[$value['id']] = array(
				'value' => $value['name'],
				'is_default' => ($value['is_default_sw'] == 'Y' ? true : false)
			);
			$data[$value['id']]['custom'] = array();
			if ($value['custom_field']) {
				$temp = explode(":::", $value['custom_field']);
				foreach ($temp as $val) {
					$temp2 = explode("::", $val);
					$data[$value['id']]['custom'][$temp2[0]] = isset($temp2[1]) ? $temp2[1] : '';
				}
			}
		}
		return $data;
	}


	/**
	 * Формирует пару ключ=>значение
	 *
	 * @param string $global_id - глобальный идентификатор справочника
	 * @param bool $name_as_id
	 * @param bool $empty_first
	 * @return array
	 */
	public function getEnumDropdown($global_id, $name_as_id = false, $empty_first = false) {
		if (!$name_as_id) $name_as_id = 'id';
		else $name_as_id = 'name';
		$data = $this->db->fetchPairs("SELECT `$name_as_id`, `name`
									FROM core_enum
									WHERE is_active_sw='Y'
									AND parent_id = (SELECT id FROM core_enum WHERE global_id=? AND is_active_sw='Y')
									ORDER BY seq",
			$global_id
		);
		if ($empty_first) {
			$data = array('' => '') + $data;
		}
		return $data;
	}


	/**
	 * Получает значение справочника по первичному ключу
	 *
	 * @param int $id
	 * @return string
	 */
	public function getEnumValueById($id) {
		$res = $this->db->fetchOne("SELECT name FROM core_enum WHERE id = ?", $id);
		return $res;
	}


	/**
	 * @param int $id
	 * @return array
	 */
	public function getEnumById($id) {
		$res = $this->db->fetchRow("SELECT id, name, custom_field, is_default_sw
									FROM core_enum
									WHERE is_active_sw = 'Y'
									AND id = ?", $id);
		if ($res['custom_field']) {
			$temp = array();
			$temp2 = explode(":::", $res['custom_field']);
			foreach ($temp2 as $fields) {
				$fields = explode("::", $fields);
				$temp[$fields[0]] = $fields[1];
			}
			$res['custom_field'] = $temp;
		}
		return $res;
	}


	/**
	 * @param int $id
	 * @return bool|string
	 */
	final public function isUserActive($id) {
		if ($id === -1) return true;
		return $this->db->fetchOne("SELECT 1 FROM core_users WHERE u_id=? AND visible='Y'", $id);
	}


	/**
	 * @param string $module_id
	 * @return string
	 */
	final public function isModuleActive($module_id) {
		$key = "is_active_" . $this->config->database->params->dbname . "_" . $module_id;
		if (!($this->cache->test($key))) {
			$is = $this->db->fetchOne("SELECT 1 FROM core_modules WHERE module_id = ? AND visible='Y'", $module_id);
			$this->cache->save($is, $key, array('is_active_core_modules'));
		} else {
			$is = $this->cache->load($key);
		}
		return $is;
	}


	/**
	 * Определяет, является ли субмодуль активным
	 * Если модуль не активен, то все его субмодели НЕ активны, в независимости от значения в БД
	 * @param $submodule_id
	 *
	 * @return string
	 */
	final public function isSubModuleActive($submodule_id) {
		$id = explode("_", $submodule_id);
		if (isset($id[1]) && $this->isModuleActive($id[0])) {
			$is = $this->db->fetchOne("SELECT 1 FROM core_modules AS m
										INNER JOIN core_submodules AS s ON s.m_id=m.m_id
									WHERE m.module_id=? AND s.sm_key=? AND s.visible='Y'",
				$id);
		} else {
			$is = 0;
		}
		return $is;
	}


	/**
	 * Получаем информацию о субмодуле
	 * @param $submodule_id
	 *
	 * @return bool|false|mixed
	 */
	public function getSubModule($submodule_id) {
		$key = "is_active_" . $this->config->database->params->dbname . "_" . $submodule_id;
		$id = explode("_", $submodule_id);
		if (empty($id[1])) {
			return false;
		}
		if (!($this->cache->test($key))) {
			$mods = $this->db->fetchRow("SELECT m.m_id, m_name, sm_path, m.module_id, is_system, sm.m_id AS sm_id
											 FROM core_modules AS m
												  LEFT JOIN core_submodules AS sm ON sm.m_id = m.m_id AND sm.visible = 'Y'
											WHERE m.visible = 'Y'
											  AND m.module_id = ?
											  AND sm_key = ?
											  ORDER BY sm.seq",
				$id);
			$this->cache->save($mods, $key, array('is_active_core_modules'));
		} else {
			$mods = $this->cache->load($key);
		}
		return $mods;
	}


	/**
	 * @param string $module_id
	 * @return string
	 */
	final public function isModuleInstalled($module_id) {
		$module_id = trim(strtolower($module_id));
		$key = "is_installed_" . $this->config->database->params->dbname . "_" . $module_id;
		if (!($this->cache->test($key))) {
			$is = $this->db->fetchOne("SELECT 1 FROM core_modules WHERE module_id = ?", $module_id);
			$this->cache->save($is, $key, array('is_active_core_modules'));
		} else {
			$is = $this->cache->load($key);
		}
		return $is;
	}


	/**
	 * Возврат абсолютного пути до директории в которой находится модуль
	 *
	 * @param string $module_id
	 * @return mixed
	 */
	final public function getModuleLocation($module_id) {
		return DOC_ROOT . $this->getModuleLoc($module_id);
	}


	/**
	 * возврат версии модуля
	 * @param string $module_id
	 * @return string
	 */
	final public function getModuleVersion($module_id) {

		return $this->db->fetchOne("
            SELECT version
            FROM core_modules
            WHERE module_id = ?
        ", $module_id);
	}


	/**
	 * Получение абсолютного адреса папки модуля
	 * @param  string $module_id
	 * @return string
	 */
	final public function getModuleSrc($module_id) {
		$loc = $this->getModuleLoc($module_id);
		return DOC_PATH . $loc;
	}


	/**
	 * Получение относительного адреса папки модуля
	 *
	 * @param $module_id
	 *
	 * @return false|mixed|string
	 * @throws Exception
	 */
	final public function getModuleLoc($module_id) {
		$module_id = trim(strtolower($module_id));
		if (!$module_id) throw new Exception($this->traslate->tr("Не определен идентификатор модуля."));
		if (!($this->cache->test($module_id))) {
			if ($module_id == 'admin') {
				$loc = "core2/mod/admin";
			} else {
				$m = $this->db->fetchRow("SELECT is_system, version FROM core_modules WHERE module_id = ?", $module_id);
				if ($m) {
					if ($m['is_system'] == "Y") {
						$loc = "core2/mod/{$module_id}/v{$m['version']}";
					} else {
						$loc = "mod/{$module_id}/v{$m['version']}";
						if (!is_dir(DOC_ROOT . $loc)) {
							$loc = "mod/{$module_id}";
						}
					}
				} else {
					throw new Exception($this->traslate->tr("Модуль не существует"), 404);
				}
			}
			$this->cache->save($loc, $module_id);
		} else {
			$loc = $this->cache->load($module_id);
		}
		return $loc;
	}


	/**
	 * @param string $module_id
	 */
	final public function getModule($module_id) {

	}


	/**
	 * @param string $name
	 * @return \Core2\Log
	 */
	final public function log($name) {

		$log = new \Core2\Log($name);
		return $log;
	}


	/**
	 * Получение всех настроек системы
	 */
	final private function getAllSettings() {
		$key = "all_settings_" . $this->config->database->params->dbname;
		if (!($this->cache->test($key))) {
			$res = $this->db->fetchAll("SELECT code, value, is_custom_sw, is_personal_sw FROM core_settings WHERE visible='Y'");
			$is = array();
			foreach ($res as $item) {
				$is[$item['code']] = array(
					'value' => $item['value'],
					'is_custom_sw' => $item['is_custom_sw'],
					'is_personal_sw' => $item['is_personal_sw']
				);
			}
			$this->cache->save($is, $key);
		} else {
			$is = $this->cache->load($key);
		}
		$this->_settings = $is;
	}
}