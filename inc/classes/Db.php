<?

require_once("Zend/Db/Table.php");
require_once("Zend/Registry.php");

class Db {
	protected $config;
	protected $frontendOptions = array(
		'lifetime'                => 40000,
		'automatic_serialization' => true
	);
	protected $backendOptions = array();
	protected $backend = 'File';

	public function __construct($config = null) {
		if (is_null($config)) {
			$this->config = Zend_Registry::getInstance()->get('config');
		} else {
			$this->config = $config;
		}
        if (!$config) return false;

	}

	public function __get($k) {
		if ($k == 'db') {
			$this->config = Zend_Registry::getInstance()->get('config');
			if (!Zend_Registry::getInstance()->isRegistered('db')) {
				$db = $this->establishConnection($this->config->database);
			} else {
				$db = Zend_Registry::getInstance()->get('db');
			}
			$v = $this->{$k} = $db;
			return $v;
		}
		// Получение указанного кэша
		if ($k == 'cache') {
			$v = $this->{$k} = Zend_Cache::factory('Core',
					$this->backend,
					$this->frontendOptions,
					array('cache_dir' => $this->config->cache));
			return $v;
		}
	}

	/**
	 * @param $database
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function establishConnection($database) {
		try {
			$db = Zend_Db::factory($database);
			Zend_Db_Table::setDefaultAdapter($db);
			$db->getConnection();
			Zend_Registry::getInstance()->set('db', $db);
			if ($this->config->system->timezone) $db->query("SET time_zone = '{$this->config->system->timezone}'");
		} catch (Zend_Db_Adapter_Exception $e) {
			Error::catchDbException($e);
		} catch (Zend_Exception $e) {
			Error::catchZendException($e);
		}
		return $db;
	}

	/**
	 * @param string $dbname
	 * @param string $username
	 * @param string $password
	 * @param string $host
	 * @param string $charset
	 * @param string $adapter
	 * @return Zend_Db_Adapter_Abstract
	 */
	public function newConnector($dbname, $username, $password, $host = 'localhost', $charset = 'utf8', $adapter = 'Pdo_Mysql') {
		$temp = array('host'     	=> $host,
					  'username' 	=> $username,
					  'password' 	=> $password,
					  'dbname'   	=> $dbname,
					  'charset'		=> $charset
					  );
		try {
			$db = Zend_Db::factory($adapter, $temp);
			$db->getConnection();
		} catch (Zend_Db_Adapter_Exception $e) {
			Error::catchDbException($e);
		} catch (Zend_Exception $e) {
			Error::catchZendException($e);
		}
	    return $db;
	}

	/**
	 * @param string $resId
	 * @return array
	 */
	public function getModuleName($resId) {
		$data = explode("_", $resId);
		if (!empty($data[1])) {
			$res = $this->db->fetchRow("SELECT m.m_name, sm.sm_name 
										  FROM core_modules AS m
												INNER JOIN core_submodules AS sm ON sm.m_id = m.m_id
										WHERE CONCAT(m.module_id, '_', sm.sm_key) = ?", $resId);
			$res = array($res['m_name'], $res['sm_name']);
		} else {
			$res = $this->db->fetchRow("SELECT m.m_name FROM core_modules AS m WHERE m.module_id = ?", $resId);
			$res = array($res['m_name']);
		}
		return $res;
	}

    /**
     * Сохранение информации о входе пользователя
     * @param string $auth
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
	 * @param string $auth
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
	 * @param Zend_Session_Namespace $auth
	 */
	public function logActivity(Zend_Session_Namespace $auth, $exclude = array()) {
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
			if ($this->config->log && $this->config->log->system->writer == 'file') {
				if (!$this->config->log->system->file) {
					throw new Exception('Не задан файл журнала');
				}
				$f = fopen($this->config->log->system->file, 'a');
				if (!is_file($this->config->log->system->file)) {
					throw new Exception('Не найден файл журнала');
				}
				//TODO rotation
				if (!$f) throw new Exception('Нет доступа на запись в файл журнала');
				$dt = date('Y-m-d H:i:s');
				$fields = array($dt) + array_values($data);
				fputcsv($f, $fields);
				fclose($f);
				$where = $this->db->quoteInto("sid=?", $data['sid']);
				$this->db->update('core_session', array('last_activity' => $dt), $where);
			} else {
				$this->db->insert('core_log', $data);
				$where = array($this->db->quoteInto("sid=?", $data['sid']),
					$this->db->quoteInto("ip=?", $data['ip']),
					$this->db->quoteInto("user_id=?", $data['user_id']),
					'logout_time IS NULL'
				);
				$this->db->update('core_session', array('last_activity' => new Zend_Db_Expr('NOW()')), $where);
			}
		}
	}

	/**
	 * @param string $code
	 * @return string
	 */
	public function getSetting($code) {
		return $this->db->fetchOne("SELECT value FROM core_settings WHERE visible='Y' AND code=? LIMIT 1", $code);
	}

	/**
	 * @param string $code
	 * @return string
	 */
	public function getCustomSetting($code) {
		return $this->db->fetchOne("SELECT value FROM core_settings WHERE visible='Y' AND is_custom_sw='Y' AND code=? LIMIT 1", $code);
	}

	/**
	 * @param string $code
	 * @return string
	 */
	public function getPersonalSetting($code) {
		return $this->db->fetchOne("SELECT value FROM core_settings WHERE visible='Y' AND is_personal_sw='Y' AND code=? LIMIT 1", $code);
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
	 * @param string $global_id
	 * @param bool $name_as_id
	 * @param bool $empty_first
	 * @return array
	 */
	public function getEnumDropdown($global_id, $name_as_id = false, $empty_first = false) {
		if (!$name_as_id) $name_as_id = 'id';
		else $name_as_id = 'name';
		$data = $this->db->fetchPairs("SELECT $name_as_id, name FROM core_enum WHERE is_active_sw='Y' AND parent_id = (SELECT id FROM core_enum WHERE global_id=? AND is_active_sw='Y') ORDER BY seq", $global_id);
		if ($empty_first) {
			$data = array('' => '') + $data;
		}
		return $data;
	}

	/**
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
	public function isUserActive($id) {
		if ($id === -1) return true;
		return $this->db->fetchOne("SELECT 1 FROM core_users WHERE u_id=? AND visible='Y'", $id);
	}

	/**
	 * @param string $module_id
	 * @return string
	 */
	public function isModuleActive($module_id) {
		return $this->db->fetchOne("SELECT 1 FROM core_modules WHERE module_id = ? AND visible='Y'", $module_id);
	}

    /**
     * Возврат абсолютного пути до директории в которой находится модуль
     *
     * @param string $module_id
     * @return mixed
     */
    public function getModuleLocation($module_id) {
        return DOC_ROOT . $this->getModuleSrc($module_id);
    }


    /** возврат версии модуля
     * @param string $module_id
     * @return string
     */
    public function getModuleVersion($module_id) {

        return $this->db->fetchOne("
            SELECT version
            FROM core_modules
            WHERE module_id = ?
        ", $module_id);
    }

	/**
	 * Получение относительного адреса папки модуля
	 * @param $module_id
	 * @return string
	 */
	public function getModuleSrc($module_id)
	{
		$loc = $this->db->fetchOne("
            SELECT CASE WHEN is_system='Y'
                      THEN CONCAT('core2/mod/', module_id, '/v', version)
                      ELSE CONCAT('mod/', module_id)
                   END
            FROM core_modules
            WHERE module_id = ?
        ", $module_id);
		return $loc;
	}

}