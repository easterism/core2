<?php
require_once 'Acl.php';

use Core2\Registry;

/**
 * Class CommonApi
 * @property StdClass        $acl
 * @property CoreController  $modAdmin
 * @property Zend_Config_Ini $moduleConfig
 */
class CommonApi extends \Core2\Acl {

    /**
     * @var StdClass|SessionContainer
     */
	protected $auth;

	private $module;
	private $_p = array();


    /**
     * CommonApi constructor.
     * @param string $module
     */
	public function __construct($module) {
		parent::__construct();
        $reg     = Registry::getInstance();

        $this->module = $module;
        if (!$reg->isRegistered('invoker')) {
            $reg->set('invoker', $this->module);
        }
		$this->auth = $reg->get('auth');
	}


    /**
     * @param string $method
     * @param array  $arguments
     * @return bool
     */
    public function __call($method, $arguments) {
        return false;
    }


    /**
     * @param string $k
     * @return bool
     */
	public function __isset($k) {
		return isset($this->_p[$k]);
	}


    /**
     * Автоматическое подключение других модулей
     * инстансы подключенных объектов хранятся в массиве $_p
     *
     * @param string $k
     * @return Common|null|Zend_Db_Adapter_Abstract|Zend_Config_Ini|CoreController|mixed
     * @throws Exception
     */
    public function __get($k) {

		$v = NULL;

		if (array_key_exists($k, $this->_p)) {
			$v = $this->_p[$k];
		} else {
			//исключение для герета базы или кеша, выполняется всегда
			if ($k == 'db' || $k == 'cache') {
                return parent::__get($k . "|" . $this->module);
			}
			// Получение экземпляра класса для работы с правами пользователей
			elseif ($k == 'acl') {
				$v = $this->{$k} = Registry::get('acl');
			}
            elseif ($k == 'modAdmin') {
                require_once(DOC_ROOT . 'core2/inc/CoreController.php');
                $v = $this->{$k} = new CoreController();
                $v->module = 'admin';
            }
			// Получение экземпляра модели текущего модуля
			elseif (strpos($k, 'data') === 0) {
                return parent::__get($k . "|" . $this->module);
			}
            elseif (strpos($k, 'api') === 0) {
                $module = substr($k, 3);

                if ($this->isModuleActive($module)) {
                    $location = $module == 'Admin'
                        ? DOC_ROOT . "core2/mod/admin"
                        : $this->getModuleLocation($module);

                    $module     = ucfirst($module);
                    $module_api = "Mod{$module}Api";

                    if ( ! file_exists("{$location}/{$module_api}.php")) {
                        return new stdObject();

                    } else {
                        $autoload_file = $location . "/vendor/autoload.php";

                        if (file_exists($autoload_file)) {
                            require_once($autoload_file);
                        }

                        require_once "{$location}/{$module_api}.php";

                        $api = new $module_api();
                        if ( ! is_subclass_of($api, 'CommonApi')) {
                            return new stdObject();
                        }

                        $v = $this->{$k} = $api;
                    }
                } else {
                    return new stdObject();
                }
            }
            elseif ($k === 'moduleConfig') {
                $module_loc = $this->getModuleLocation($this->module);
                $conf_file  = "{$module_loc}/conf.ini";
                if (is_file($conf_file)) {
                    $config_mod = $this->getModuleConfig($this->module);
                    $v = $this->{$k} = $config_mod;
                } else {
                    \Core2\Error::Exception($this->_("Не найден конфигурационный файл модуля."), 500);
                }
            }
			else {
				$v = $this->{$k} = $this;
			}
		}

		return $v;
	}


    /**
     * @param string $k
     * @param mixed  $v
     * @return $this
     */
	public function __set($k, $v) {
		$this->_p[$k] = $v;
		return $this;
	}
}
