<?php
declare(strict_types=1);
namespace Core2;

require_once "Cache.php";
require_once "Log.php";
require_once "WorkerClient.php";
require_once 'Fact.php';

use Laminas\Cache\Storage;
use Laminas\Session\Container as SessionContainer;
use Laminas\Config\Config as LaminasConfig;
use Core2\Config as CoreConfig;

/**
 * Class Db
 * @property \Zend_Db_Adapter_Abstract $db
 * @property Cache                     $cache
 * @property I18n                      $translate
 * @property Log                       $log
 * @property Fact                      $fact
 * @property \CoreController           $modAdmin
 * @property Model\Session             $dataSession
 * @property LaminasConfig             $core_config
 * @property WorkerClient              $workerAdmin
 */
class Db {

    /**
     * @var LaminasConfig
     */
    protected $config;

    /**
     * @var LaminasConfig
     */
    private $_core_config;

    private $_locations = array();
    private string $schemaName = 'public';

    /**
     * Db constructor.
     * @param null $config
     * @throws \Zend_Exception
     */
	public function __construct($config = null) {

	    if (is_null($config)) {
			$this->config = Registry::get('config');
		} else {
			$this->config = $config;
		}

	}

	/**
	 * @param string $k
	 * @return mixed|LaminasConfig|\Zend_Db_Adapter_Abstract|Log
	 * @throws \Zend_Exception
	 * @throws \Exception
	 */
	public function __get($k) {
        $reg      = Registry::getInstance();
        $module = isset($this->module) ? $this->module : 'admin';
        $k_module = $k . "|" . $module;

		if ($k == 'core_config') {
            if (!$this->_core_config) $this->_core_config = $reg->get('core_config');
            return $this->_core_config;
        }
		if ($k == 'db') {
//            if ($reg->isRegistered('invoker')) {
//                $k_module = $k . "|" . $reg->get('invoker');
//            }
            if (!$reg->isRegistered($k_module)) {
                if (!$this->config) $this->config = $reg->get('config');
                if (!$this->_core_config) $this->_core_config = $reg->get('core_config');

                if ($module !== 'admin') {
                    $module_config = $this->getModuleConfig($module);

                    if ($module_config && $module_config->database) {
                        // у этого модуля собственный адаптер
                        $db = $this->establishConnection($module_config->database);
                        $reg->set($k_module, $db);
                        return $db;
                    } else {
                        $reg->set($k_module, 'db'); //храним в реестре только указание, что $k_module будет использовать подключение по умолчанию
                        if ($reg->isRegistered('db|admin')) {
                            $db = $reg->get('db|admin');
                            return $db;
                        }
                    }
                }
                //подключение к базе по умолчанию (выполняется 1 раз)
                $db = $this->establishConnection($this->config->database);
                \Zend_Db_Table::setDefaultAdapter($db);
                $reg->set('db|admin', $db);

            }
			$db = $reg->get($k_module);
            if ($db === 'db') $db = $reg->get('db|admin');
			return $db;
		}
		// Получение указанного кэша
		if ($k == 'cache') {
            //$k = $k . '|';
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
			}
            else {
				$v = $reg->get($k);
			}
			return $v;
		}
		// Получение экземпляра переводчика
        elseif ($k == 'translate') {
			return $reg->get($k);
		}
		// Получение экземпляра логера
		elseif ($k == 'log') {
            $k = $k . '|';
            if (!$reg->isRegistered($k)) {
                $v = new Log();
                $reg->set($k, $v);
            } else {
                $v = $reg->get($k);
            }
			return $v;
		}
        // Получение экземпляра воркера
        elseif (strpos($k, 'worker') === 0) {
            if (!$reg->isRegistered('worker|')) {
                $v = new WorkerClient();
                $reg->set('worker|', $v);
            } else {
                $v = $reg->get('worker|');
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
            if ($reg->isRegistered($k)) {
                $v = $reg->get($k);
			} else {

				$model    = substr($k, 4);
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
                $db = $this->db; ////FIXME грязный хак для того чтобы сработал сеттер базы данных. Потому что иногда его здесь еще нет, а для инициализаци модели используется адаптер базы данных по умолчанию
                if ($module == 'admin') $model = "\Core2\Model\\$model";
//                else {
//                    $module_config = $this->getModuleConfig($module);
//                    if ($module_config && $module_config->database) {
//                        $db = $this->getConnection($module_config->database);
//                    }
//                }
                $v            = new $model($db);
                $reg->set($k, $v);
			}
			return $v;
		}
        // Получение экземпляра регистратора фактов
        elseif ($k == 'fact') {
            $k = $k . '|';
            if (!$reg->isRegistered($k)) {
                $v = new Fact();
                $reg->set($k, $v);
            } else {
                $v = $reg->get($k);
            }
            return $v;
        }
		return null;
	}


    /**
     * @param LaminasConfig $database
     * @return \Zend_Db_Adapter_Abstract
     */
    private function establishConnection(LaminasConfig $database) {
		try {
            $db = $this->getConnection($database);

            //переопределяем config для нового подключения к базе
            if ($this->config->database !== $database) {
                $conf = $this->config->toArray();
                $conf['database'] = $database->toArray();
                $this->config = (new CoreConfig($conf))->getData();
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
     * @param LaminasConfig $database
     * @return \Zend_Db_Adapter_Abstract
     * @throws \Zend_Db_Exception
     */
	protected function getConnection(LaminasConfig $database) {
        if ($database->adapter === 'Pdo_Mysql') {
            $this->schemaName = $database->params->dbname;
        }
        elseif ($database->adapter === 'Pdo_Pgsql') {
            $this->schemaName = $database->schema;
        }
        Registry::set('dbschema', $this->schemaName);
        $db = \Zend_Db::factory($database->adapter, $database->params->toArray());
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
	public function getModuleName(string $resId): array {
        $module_id = explode('_', $resId);
        $module = $this->getModule($module_id[0]);
        if (!$module) return [];

        $data = [$module['m_name']];
		if (!empty($module_id[1])) {
            if (!isset($module['submodules']) || !isset($module['submodules'][$module_id[1]])) return []; //модуль есть а субмодуля нет

            $data[] = $module['submodules'][$module_id[1]]['sm_name'];
		}
		return $data;
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

        $auth = Registry::get('auth');

        if ($auth->ID && $auth->ID > 0) {
            if ($exclude && in_array($_SERVER['QUERY_STRING'], $exclude)) {
                return;
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
            $w = $this->workerAdmin->doBackground('Logger', $data);
            if ($w) {
                return;
            }

            if (isset($this->config->log) &&
                $this->config->log &&
                isset($this->config->log->access->writer) &&
                $this->config->log->access->writer == 'file'
            ) {
                if ( ! $this->config->log->access->file) {
                    throw new \Exception($this->translate->tr('Не задан файл журнала запросов'));
                }

                $log = new Log('access');
                $log->access($auth->NAME, $data['sid']);

            } else {
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
		return isset(Registry::get("_settings")[$code]) ? Registry::get("_settings")[$code]['value'] : false;
	}


	/**
	 * @param string $code
	 * @return string
	 */
	public function getCustomSetting($code) {
		$this->getAllSettings();
        $sett = Registry::get("_settings");
		if (isset($sett[$code]) && $sett[$code]['is_custom_sw'] == 'Y') {
			return $sett[$code]['value'];
		}
		return false;
	}


	/**
	 * @param string $code
	 * @return string
	 */
	public function getPersonalSetting($code) {
		$this->getAllSettings();
        $sett = Registry::get("_settings");
		if (isset($sett[$code]) && $sett[$code]['is_personal_sw'] == 'Y') {
			return $sett[$code]['value'];
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
	final public function isModuleActive(string $module_id): bool {
        $id = explode("_", strtolower($module_id));
        $mod = $this->getModule($id[0]);
        if (!$mod) return false;
        if (!empty($id[1])) {
            if (empty(Registry::get("_modules")[$id[0]]['submodules'][$id[1]]) ||
                Registry::get("_modules")[$id[0]]['submodules'][$id[1]]['visible'] !== 'Y') return false;
        }
        return true;
	}


    /**
     * Получаем информацию о субмодуле
     * @param string $submodule_id
     * @return array|false
     */
    final public function getSubModule(string $submodule_id): array {

        $this->getAllModules();
        $id = explode("_", $submodule_id);
        $_mods = Registry::get("_modules");

        if (empty($id[1]) ||
            empty($_mods[$id[0]]) ||
            empty($_mods[$id[0]]['submodules'][$id[1]])
        ) {
            return [];
        }

        $submodule = $_mods[$id[0]]['submodules'][$id[1]];

        return [
            'm_id'      => $_mods[$id[0]]['m_id'],
            'm_name'    => $_mods[$id[0]]['m_name'],
            'sm_path'   => $submodule['sm_path'],
            'sm_name'   => $submodule['sm_name'],
            'visible'   => $submodule['visible'],
            'module_id' => $id[0],
            'is_system' => $_mods[$id[0]]['is_system'],
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
        $module_id = trim(strtolower($module_id));
        $is = isset(Registry::get("_modules")[$module_id]) ? Registry::get("_modules")[$module_id] : [];
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
        $module_id = strtolower($module_id);
        $reg      = Registry::getInstance();
        if (!$reg->isRegistered("location_ " . $module_id)) {

//            $config = $reg->get('config');
//            $db = $this->establishConnection($config->database);
            $mod = $this->db->fetchRow("SELECT * FROM core_modules WHERE module_id=?", $module_id);
            if (!$mod) return false;
            if ($mod['is_system'] === "Y") {
                $location = __DIR__ . "/../../mod/{$module_id}/v{$mod['version']}";
            } else {
                $location = DOC_ROOT . "mod/{$module_id}/v{$mod['version']}";
            }
            $reg->set("location_ " . $module_id, $location);
        } else {
            $location = $reg->get("location_ " . $module_id);
        }
		return $location;
		//return DOC_ROOT . $this->getModuleLoc($module_id);
	}


	/**
	 * возврат версии модуля
	 * @param string $module_id
	 * @return string
	 */
	final public function getModuleVersion($module_id) {
        $this->getAllModules();
        $version = isset(Registry::get("_modules")[$module_id]) ? Registry::get("_modules")[$module_id]['version'] : '';
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
            if ($module_id === 'admin') {
                $loc = "core2/mod/admin";
            } else {
                if ( !$module) {
                    throw new \Exception($this->translate->tr("Модуль не существует") . ": " . $module_id, 404);
                }
                if ($module['is_system'] === "Y") {
                    $loc = "core2/mod/{$module_id}/v{$module['version']}";
                } else {
                    $loc = "mod/{$module_id}/v{$module['version']}";
                    if ( ! is_dir(DOC_ROOT . $loc)) {
                        $loc = "mod/{$module_id}";
                    }
                }
            }
            $_mods = Registry::get("_modules");
            $_mods[$module_id]['location'] = $loc;
            Registry::set("_modules", $_mods);
        } else {
            $loc = $module['location'];
        }
        $this->_locations[$module_id] = $loc;
        return $loc;
	}


	/**
	 * @param string $module_id
	 */
	final public function getModule(string $module_id): array {
        $this->getAllModules();
        return isset(Registry::get("_modules")[$module_id]) ? Registry::get("_modules")[$module_id] : [];
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
     * @return false|LaminasConfig
     * @throws \Exception
     */
    final protected function getModuleConfig(string $name) {

        $module_loc = $name == 'admin'
            ? __DIR__ . "/../../mod/admin"
            : $this->getModuleLocation($name);
        $conf_file  = "{$module_loc}/conf.ini";
        if (is_file($conf_file)) {

            $config    = new CoreConfig();
            $section   = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';
            $config2   = $config->readIni($conf_file, $section);
            if (!$config2->toArray()) {
                $config2   = $config->readIni($conf_file, 'production');
            }
            $conf_d = $module_loc . "conf.ext.ini";
            if (file_exists($conf_d)) {
                $config2->merge($config->readIni($conf_d, $section));
            }
            $config2->setReadOnly();
            return $config2;

        } else {
            return false;
        }
    }


	/**
	 * Получение всех включенных настроек системы
	 */
	private function getAllSettings(): void {
        $reg      = Registry::getInstance();
        if ($reg->isRegistered("_settings")) return;
		$key = "all_settings_" . $this->config->database->params->dbname;
		if (!($this->cache->hasItem($key))) {
            require_once(__DIR__ . "/../../mod/admin/Model/Settings.php");
            $v   = new Model\Settings($this->db);
			$res = $v->fetchAll($v->select()->where("visible='Y'"))->toArray();
            $is  = array();
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
        $reg->set("_settings", $is);
	}

    /**
     * Список всех модулей
     *
     * @return void
     */
    private function getAllModules(): void {
        $reg      = Registry::getInstance();
        if ($reg->isRegistered("_modules")) return;
        $key2 = "all_modules_" . $this->config->database->params->dbname;
        //if (1==1) {
        if (!($this->cache->hasItem($key2))) {
            require_once(__DIR__ . "/../../mod/admin/Model/Modules.php");
            require_once(__DIR__ . "/../../mod/admin/Model/SubModules.php");
            $config = $reg->get('config');
            $db = $this->establishConnection($config->database);
            \Zend_Db_Table::setDefaultAdapter($db);
            $reg->set('db|admin', $db);

            $m            = new Model\Modules($db);
            $sm           = new Model\SubModules($db);
            $res    = $m->fetchAll($m->select()->order('seq'))->toArray();
            $sub    = $sm->fetchAll($sm->select()->order('seq'));
            $data   = [];
            foreach ($res as $val) {
                unset($val['uninstall']); //чтоб не смущал
                unset($val['files_hash']); //чтоб не смущал
                $val['submodules'] = [];

                foreach ($sub as $item2) {
                    if ($item2->m_id == $val['m_id']) {
                        $val['submodules'][$item2->sm_key] = $item2->toArray();
                    }
                }
                $data[$val['module_id']] = $val;
            }
            if ($data) $this->cache->setItem($key2, $data);
            else {
                //такого быть не может
            }
        } else {
            $data = $this->cache->getItem($key2);
        }
        $reg->set("_modules", $data);
    }

}