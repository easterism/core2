<?php
namespace Core2;

require_once "Cache.php";
require_once "Log.php";
require_once "WorkerClient.php";
require_once 'Zend_Registry.php';
use Laminas\Cache\Storage;
use Laminas\Session\Container as SessionContainer;

/**
 * Class Db
 * @property \Zend_Db_Adapter_Abstract $db
 * @property Cache                     $cache
 * @property I18n                      $translate
 * @property Log                       $log
 * @property \CoreController           $modAdmin
 * @property \Session                  $dataSession
 * @property \Zend_Config_Ini          $core_config
 * @property WorkerClient              $workerAdmin
 */
class Db {

    /**
     * @var \Zend_Config_Ini
     */
    protected $config;

    /**
     * @var \Zend_Config_Ini
     */
    private $_core_config;

    private $_s         = array();
    private $_settings  = array();
    private $_locations = array();
    private $_modules = array();
    private string $schemaName = 'public';

    /**
     * Db constructor.
     * @param null $config
     * @throws \Zend_Exception
     */
	public function __construct($config = null) {

	    if (is_null($config)) {
			$this->config = \Zend_Registry::get('config');
		} else {
			$this->config = $config;
		}

		if ( ! $config) {
		    return false;
        }
	}


	/**
	 * @param string $k
	 * @return mixed|\Zend_Cache_Core|\Zend_Db_Adapter_Abstract|Log
	 * @throws \Zend_Exception
	 * @throws \Exception
	 */
	public function __get($k) {
		if ($k == 'core_config') {
            $reg                = \Zend_Registry::getInstance();
            $this->_core_config = $reg->get('core_config');
            return $this->_core_config;
        }
		if ($k == 'db') {
			$reg = \Zend_Registry::getInstance();
			if (!$reg->isRegistered('db')) {
				if (!$this->config) $this->config = $reg->get('config');
				if (!$this->_core_config) $this->_core_config = $reg->get('core_config');
				$db = $this->establishConnection($this->config->database);
			} else {
				$db = $reg->get('db');
                $this->schemaName = $reg->get('dbschema');
			}
			return $db;
		}
		// Получение указанного кэша
		if ($k == 'cache') {
			$reg = \Zend_Registry::getInstance();
			if (!$reg->isRegistered($k)) {
                if (!$this->_core_config) $this->_core_config = $reg->get('core_config');
                $options = $this->_core_config->cache->options ? $this->_core_config->cache->options->toArray() : [];
                $adapter_name = !empty($this->_core_config->cache->adapter) ? $this->_core_config->cache->adapter : 'Filesystem';
                if (isset($this->config->cache->adapter)) {
                    $adapter_name = $this->config->cache->adapter;
                    $options = $this->config->cache->options->toArray();
                }
                else { //DEPRECATED
                    if ($adapter_name == 'Filesystem' && $this->config->cache) { //если кеш задан в основном конфиге
                        $options['cache_dir'] = $this->config->cache;
                    }
                }
                $options['namespace'] = "Core2";
                //$container = null; // can be any configured PSR-11 container
				//$sf = $container->get(StorageAdapterFactoryInterface::class);
                if ($adapter_name == 'Filesystem') {
                    $adapter  = new Storage\Adapter\Filesystem($options);
                }
                if ($adapter_name == 'Redis') {
                    $options['namespace'] = $_SERVER['SERVER_NAME'] . ":Core2";
                    if (!empty($options['database'])) $options['namespace'] .= ":" . $options['database'];
                    unset($options['cache_dir']);
                    $adapter  = new Storage\Adapter\Redis($options);
                }
                $adapter->addPlugin(new Storage\Plugin\Serializer());
                $plugin = new Storage\Plugin\ExceptionHandler();
                $plugin->getOptions()->setThrowExceptions(false);
                $adapter->addPlugin($plugin);

                $v = new Cache($adapter, $adapter_name);
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
				$v = \Zend_Registry::get('translate');
				$this->_s[$k] = $v;
			}
			return $v;
		}
		// Получение экземпляра логера
		elseif ($k == 'log') {
			if (array_key_exists($k, $this->_s)) {
				$v = $this->_s[$k];
			} else {
				$v = new Log();
				$this->_s[$k] = $v;
			}
			return $v;
		}
        // Получение экземпляра воркера
        elseif (strpos($k, 'worker') === 0) {
            if (array_key_exists('worker', $this->_s)) {
                $v = $this->_s['worker'];
            } else {
//                if ($this->db->getTransactionLevel()) {
//                    throw new \Exception($this->translate->tr("You can't use worker until database is on transaction."));
//                }
//                $this->db->closeConnection();
                $v = new WorkerClient();
                $this->_s['worker'] = $v;
            }

            $module = substr($k, 6);

            if ($module == 'Admin') {
                $v->setModule($module);
                $v->setLocation(DOC_ROOT . "core2/mod/admin");
            }
            elseif ($this->isModuleActive($module)) {
                $v->setModule($module);
                $v->setLocation($this->getModuleLocation($module));
            }
            else {
                return new \stdObject();
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
					? __DIR__ . "/../../mod/admin"
					: $this->getModuleLocation($module);
                $r = new \ReflectionClass(get_called_class());
                $classLoc = $r->getFileName();
                $classPath = strstr($classLoc, '/mod/');
                if ($classPath && strpos($classPath, dirname(strstr($location, '/mod/'))) !== 0) {
                    //происходит если модель вызывается из метода, который был вызван из другого модуля
                    $classPath = substr($classPath, 5);
                    $module    = substr($classPath, 0, strpos($classPath, "/"));
                    $location  = $this->getModuleLocation($module);
                }
				if (!file_exists($location . "/Model/$model.php")) {
                    throw new \Exception($this->translate->tr("Модель $model не найдена."));
                }
				require_once($location . "/Model/$model.php");
                if ($module == 'admin') $model = "\Core2\Model\\$model";
                $v            = new $model();
                $this->_s[$k] = $v;
			}
			return $v;
		}
		return null;
	}


    /**
     * @param \Zend_Config $database
     * @return \Zend_Db_Adapter_Abstract
     */
    private function establishConnection(\Zend_Config $database) {
		try {
            $db = $this->getConnection($database);
			\Zend_Db_Table::setDefaultAdapter($db);
			\Zend_Registry::getInstance()->set('db', $db);

            //переопределяем config для нового подключения к базе
            if ($this->config->database !== $database) {
                $conf = $this->config->toArray();
                $conf['database'] = $database->toArray();
                $this->config = new \Zend_Config($conf);
            }

			if ($database->adapter === 'Pdo_Mysql') {
			    if ($this->config->system->timezone) {
			        $db->query("SET time_zone = '{$this->config->system->timezone}'");
                }

			    if ($database->sql_mode) {
			        $db->query("SET SESSION sql_mode = ?", $database->sql_mode);
                }

                //set profiler
                if ($this->_core_config && $this->_core_config->profile && $this->_core_config->profile->on) {
                    $db->query("set profiling=1");
                    $db->query("set profiling_history_size = 100");
                }
            }
            elseif ($database->adapter === 'Pdo_Pgsql') {
                $db->query("SET search_path TO $this->schemaName");
            }
            return $db;
        } catch (\Zend_Db_Adapter_Exception $e) {
            Error::catchDbException($e);
        } catch (\Zend_Exception $e) {
            Error::catchZendException($e);
        }
	}

    /**
     * получаем соединение с базой данных
     *
     * @param \Zend_Config $database
     * @return \Zend_Db_Adapter_Abstract
     * @throws \Zend_Db_Exception
     */
	protected function getConnection(\Zend_Config $database) {
        if ($database->adapter === 'Pdo_Mysql') {
            $this->schemaName = $database->params->dbname;
        }
        elseif ($database->adapter === 'Pdo_Pgsql') {
            $this->schemaName = $database->schema;
        }
        \Zend_Registry::getInstance()->set('dbschema', $this->schemaName);
        $db = \Zend_Db::factory($database);
        $db->getConnection();
        return $db;
    }

    /**
     * получаем имя схемы базы данных
     * для Mysql это тоже самое что имя базы данных
     *
     * @return string
     */
    protected function getDbSchema() {
        return $this->schemaName;
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
	 * @return \Zend_Db_Adapter_Abstract|bool
	 */
	public function newConnector($dbname, $username, $password, $host = 'localhost', $charset = 'utf8', $adapter = 'Pdo_Mysql', $adapterNamespace = 'Core_Db_Adapter') {
	    $host = explode(":", $host);
		$temp = array(
			'host'     => $host[0],
			'port'     => isset($host[1]) ? $host[1] : 3306,
			'username' => $username,
			'password' => $password,
			'dbname'   => $dbname,
			'charset'  => $charset,
            'adapterNamespace' => $adapterNamespace
		);
		try {
			$db = \Zend_Db::factory($adapter, $temp);
			$db->getConnection();
            return $db;
        } catch (\Zend_Db_Adapter_Exception $e) {
            Error::catchDbException($e);
        } catch (\Zend_Exception $e) {
            Error::catchZendException($e);
        }

        return false;
	}


	/**
	 * @param string $resId
	 * @return array
	 */
	public function getModuleName($resId) {
		if ( ! ($this->cache->hasItem('module_name'))) {
			$res = $this->db->fetchAll("
                    SELECT m.m_name,
                           sm.sm_name,
                           m.module_id, 
                           sm.sm_key
                    FROM core_modules AS m
                        LEFT JOIN core_submodules AS sm ON sm.m_id = m.m_id");
            $data = [];
            foreach ($res as $re) {
                $data[$re['module_id']] = [$re['m_name']];
            }
            foreach ($res as $re) {
                if ($re['sm_key']) $data[$re['module_id'] . "_" . $re['sm_key']] = [$re['m_name'], $re['sm_name']];
            }
            $this->cache->setItem('module_name', $data);
		} else {
            $data = $this->cache->getItem('module_name');
		}
		$res = isset($data[$resId]) ? $data[$resId] : [];
		return $res;
	}


    /**
     * @param string $expired
     * @throws \Zend_Db_Table_Exception
     */
	public function closeSession($expired = 'N'): void {

		$auth = new SessionContainer('Auth');

		if ($auth && $auth->ID && $auth->LIVEID) {
            $row = $this->dataSession->find($auth->LIVEID)->current();

            if ($row) {
                $row->logout_time = new \Zend_Db_Expr('NOW()');
                $row->is_expired_sw = $expired;
                $row->save();
            }
        }

		$auth->getManager()->destroy();
    }


	/**
	 * Логирование активности простых пользователей
	 * @param array $exclude исключения адресов
	 * @throws \Exception
	 */
	public function logActivity($exclude = array()): void {

        $auth = \Zend_Registry::get('auth');

        if ($auth->ID && $auth->ID > 0) {
            if ($exclude && in_array($_SERVER['QUERY_STRING'], $exclude)) {
                return;
            }

            $arr = [];
            if ( ! empty($_POST)) {
                $arr['POST'] = $_POST;
            }

            if ( ! empty($_GET)) {
                $arr['GET'] = $_GET;
            }

            $data = [
                'ip'             => $_SERVER['REMOTE_ADDR'],
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'remote_port'    => $_SERVER['REMOTE_PORT'],
                'query'          => $_SERVER['QUERY_STRING'],
                'user_id'        => $auth->ID
            ];

            // обновление записи о последней активности
            if ($auth->LIVEID) {
                // у юзера есть сессия
                $data['sid'] = $auth->getManager()->getId();
                $row = $this->dataSession->find($auth->LIVEID)->current();
                if ($row) {
                    $row->last_activity = new \Zend_Db_Expr('NOW()');
                    $row->save();
                }
            } else {
                // сессии нет, авторизовывается каждый запрос
                $data['sid'] = ""; //TODO сохранить метод авторизации
            }

            // запись данных запроса в лог
            $w = $this->workerAdmin->doBackground('Logger', array_merge($data, ['action' => $arr]));
            if ($w) {
                return;
            }

            if (isset($this->config->log) &&
                $this->config->log &&
                isset($this->config->log->system->writer) &&
                $this->config->log->system->writer == 'file'
            ) {
                if ( ! $this->config->log->system->file) {
                    throw new \Exception($this->translate->tr('Не задан файл журнала запросов'));
                }

                $log = new Log('access');
                $log->access($auth->NAME, $data['sid']);

            } else {
                if ($arr) {
                    $data['action'] = serialize($arr);
                }
                $this->db->insert('core_log', $data);
            }
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
     * Получаем список значений справочника
     *
	 * @param string $global_id - глобальный идентификатор справочника
     * @param bool $active - только активные записи
	 * @return array
	 */
	public function getEnumList($global_id, $active = true) {
		$res = $this->modAdmin->dataEnum->getEnum($global_id);
		$data = array();
		foreach ($res as $id => $value) {
		    if ($active && $value['is_active_sw'] !== 'Y') continue;
            $data[$id] = $value;
        }
		return $data;
	}

	/**
	 * Формирует пару ключ=>значение
	 *
	 * @param string $global_id - глобальный идентификатор справочника
	 * @param bool $name_as_id - использовать имя в качестве значения списка
	 * @param bool $empty_first - добавлять пустое значение вначале списка
	 * @param bool $active - только активные записи
	 * @return array
	 */
	public function getEnumDropdown($global_id, $name_as_id = false, $empty_first = false, $active = true) {
        $res = $this->modAdmin->dataEnum->getEnum($global_id);
        $data = array();
        foreach ($res as $id => $value) {
            if ($active && $value['is_active_sw'] !== 'Y') continue;

            if ($name_as_id) $data[$value['value']] = $value['value'];
            else $data[$id] = $value['value'];
        }
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
     * Получаем структуру конкретного справочника
	 * @param int $id
	 * @return array
	 */
	public function getEnumById($id) {

		$enum = $this->db->fetchRow("
            SELECT id, 
                   name, 
                   custom_field, 
                   is_default_sw
			FROM core_enum
			WHERE is_active_sw = 'Y'
			  AND id = ?
        ", $id);

		if ($enum && $enum['custom_field']) {
            $custom_fields         = [];
            $custom_fields_explode = explode(":::", $enum['custom_field']);

            foreach ($custom_fields_explode as $fields) {
                $fields = explode("::", $fields);
                if (isset($fields[0]) && isset($fields[1])) {
                    $custom_fields[$fields[0]] = $fields[1];
                }
            }

            $enum['custom_field'] = $custom_fields;
        }

        return $enum;
	}


    /**
     * Ищет перевод для строки $str
     * @param string $str
     * @param string $module
     * @return string
     */
    public function _($str, $module = 'core2') {
        return $this->translate->tr($str, $module);
    }


	/**
     * Активна ли учетка юзера
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
        $is = $this->isModuleInstalled($module_id);
        return $is && isset($is['visible']) && $is['visible'] === 'Y' ? true : false;
	}


	/**
	 * Определяет, является ли субмодуль активным
	 * Если модуль не активен, то все его субмодели НЕ активны, в независимости от значения в БД
	 * @param $submodule_id
	 *
	 * @return string
	 */
	final public function isSubModuleActive($submodule_id) {
        $mod = $this->getSubModule($submodule_id);
		if ($mod) {
			$is = 1;
		} else {
			$is = 0;
		}
		return $is;
	}


    /**
     * Получаем информацию о субмодуле
     * @param string $submodule_id
     * @return array|false
     */
    public function getSubModule(string $submodule_id) {

        $this->getAllModules();
        $id = explode("_", $submodule_id);

        if (empty($id[1]) ||
            ! empty($this->_modules[$id[0]]) ||
            $this->_modules[$id[0]]['visible'] !== 'Y' ||
            ! empty($this->_modules[$id[0]]['submodules'][$id[1]]) ||
            $this->_modules[$id[0]]['submodules'][$id[1]]['visible'] !== 'Y'
        ) {
            return false;
        }

        $submodule = $this->_modules[$id[0]]['submodules'][$id[1]];

        return [
            'm_id'      => $this->_modules[$id[0]]['m_id'],
            'm_name'    => $this->_modules[$id[0]]['m_name'],
            'sm_path'   => $submodule['sm_path'],
            'sm_name'   => $submodule['sm_name'],
            'module_id' => $id[0],
            'is_system' => $this->_modules[$id[0]]['is_system'],
            'sm_id'     => $id[1],
        ];
    }


	/**
     * Проверяем, установлен ли модуль
	 * @param string $module_id
	 * @return string
	 */
	final public function isModuleInstalled($module_id) {
        $this->getAllModules();
        $is = isset($this->_modules[strtolower($module_id)]) ? $this->_modules[strtolower($module_id)] : [];
        return $is;
	}


    /**
     * Возврат абсолютного пути до директории в которой находится модуль
     *
     * @param string $module_id
     * @return mixed
     * @throws \Exception
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
        $this->getAllModules();
        $version = isset($this->_modules[$module_id]) ? $this->_modules[$module_id]['version'] : '';
		return $version;
	}


    /**
     * Получение абсолютного адреса папки модуля
     * @param  string $module_id
     * @return string
     * @throws \Exception
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
	 * @throws \Exception
	 */
	final public function getModuleLoc($module_id) {

        $module_id = trim(strtolower($module_id));
        if ( ! $module_id) throw new \Exception($this->translate->tr("Не определен идентификатор модуля."));
        if ( ! empty($this->_locations[$module_id])) return $this->_locations[$module_id];
        $module = $this->isModuleInstalled($module_id);

        if ( ! isset($module['location'])) {
            $key = "all_modules_" . $this->config->database->params->dbname;
            if ($module_id === 'admin') {
                $loc = "core2/mod/admin";
            } else {
                if ( ! $module) throw new \Exception($this->translate->tr("Модуль не существует") . ": " . $module_id, 404);
                if ($module['is_system'] === "Y") {
                    $loc = "core2/mod/{$module_id}/v{$module['version']}";
                } else {
                    $loc = "mod/{$module_id}/v{$module['version']}";
                    if ( ! is_dir(DOC_ROOT . $loc)) {
                        $loc = "mod/{$module_id}";
                    }
                }
            }
            $fromCache                         = $this->cache->getItem($key);
            $fromCache[$module_id]['location'] = $loc;
            $this->cache->setItem($key, $fromCache);
            $this->cache->setTags($key, ['is_active_core_modules']);
        } else {
            $loc = $module['location'];
        }

        $this->_locations[$module_id] = $loc;
        return $loc;
	}


	/**
	 * @param string $module_id
	 */
	final public function getModule($module_id) {

	}


    /**
     * Получаем экземпляр логера
     * @param string $name
     * @return Log
     * @throws \Exception
     */
	final public function log($name = 'core2') {

		$log = new Log($name);
		return $log;
	}


    /**
     * Получение конфигурации модуля
     * @param string $name
     * @return false|\Zend_Config_Ini
     * @throws \Zend_Config_Exception
     * @throws \Exception
     */
    final protected function getModuleConfig(string $name) {

        $module_loc = $this->getModuleLocation($name);
        $conf_file  = "{$module_loc}/conf.ini";

        if (is_file($conf_file)) {
            $config_glob  = new \Zend_Config_Ini(DOC_ROOT . 'conf.ini');
            $extends_glob = $config_glob->getExtends();

            $config_mod  = new \Zend_Config_Ini($conf_file);
            $extends_mod = $config_mod->getExtends();
            $section_mod = ! empty($_SERVER['SERVER_NAME']) &&
            array_key_exists($_SERVER['SERVER_NAME'], $extends_mod) &&
            array_key_exists($_SERVER['SERVER_NAME'], $extends_glob)
                ? $_SERVER['SERVER_NAME']
                : 'production';

            $config_mod = new \Zend_Config_Ini($conf_file, $section_mod, true);

            $conf_ext = $module_loc . "/conf.workers.ini";
            if (file_exists($conf_ext)) {
                $config_mod_ext  = new \Zend_Config_Ini($conf_ext);
                $extends_mod_ext = $config_mod_ext->getExtends();

                $section_ext = ! empty($_SERVER['SERVER_NAME']) &&
                array_key_exists($_SERVER['SERVER_NAME'], $extends_glob) &&
                array_key_exists($_SERVER['SERVER_NAME'], $extends_mod_ext)
                    ? $_SERVER['SERVER_NAME']
                    : 'production';
                $config_mod->merge(new \Zend_Config_Ini($conf_ext, $section_ext));
            }


            $config_mod->setReadOnly();
            return $config_mod;

        } else {
            return false;
        }
    }


	/**
	 * Получение всех включенных настроек системы
	 */
	private function getAllSettings(): void {
		$key = "all_settings_" . $this->config->database->params->dbname;
		if (!($this->cache->hasItem($key))) {
            require_once(__DIR__ . "/../../mod/admin/Model/Settings.php");
            $v            = new Model\Settings($this->db);
			$res = $v->fetchAll($v->select()->where("visible='Y'"))->toArray();
            $is = array();
            foreach ($res as $item) {
                $is[$item['code']] = array(
					'value' => $item['value'],
					'is_custom_sw' => $item['is_custom_sw'],
					'is_personal_sw' => $item['is_personal_sw']
				);
			}
			$this->cache->setItem($key, $is);
		} else {
			$is = $this->cache->getItem($key);
		}
		$this->_settings = $is;
	}

    /**
     * Список всех модулей
     *
     * @return void
     */
    private function getAllModules(): void {
        if ($this->_modules) return;
        $key = "all_modules_" . $this->config->database->params->dbname;
        if (!($this->cache->hasItem($key))) {
            require_once(__DIR__ . "/../../mod/admin/Model/Modules.php");
            require_once(__DIR__ . "/../../mod/admin/Model/SubModules.php");
            $m            = new Model\Modules($this->db);
            $sm           = new Model\SubModules($this->db);
            $res = $m->fetchAll($m->select()->order('seq'));
            $sub = $sm->fetchAll($sm->select()->order('seq'));
            $data = [];
            foreach ($res as $val) {
                $item = $val->toArray();
                unset($item['uninstall']); //чтоб не смущал
                $item['submodules'] = [];

                foreach ($sub as $item2) {
                    if ($item2->m_id == $val->m_id) {
                        $item['submodules'][$item2->sm_key] = $item2->toArray();
                    }
                }
                $data[$val->module_id] = $item;
            }
            $this->cache->setItem($key, $data);
        } else {
            $data = $this->cache->getItem($key);
        }
        $this->_modules = $data;
    }

}