<?php
require_once 'Acl.php';


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
		$this->module = $module;
		$this->auth = Zend_Registry::get('auth');
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
				return parent::__get($k);
			}
			// Получение экземпляра класса для работы с правами пользователей
			elseif ($k == 'acl') {
				$v = $this->{$k} = Zend_Registry::getInstance()->get('acl');
			}
            elseif ($k == 'modAdmin') {
                require_once(DOC_ROOT . 'core2/inc/CoreController.php');
                $v = $this->{$k} = new CoreController();
                $v->module = 'admin';
            }
			// Получение экземпляра модели текущего модуля
			elseif (strpos($k, 'data') === 0) {
				$model    = substr($k, 4);
				$location = $this->module == 'admin'
						? DOC_ROOT . "core2/mod/admin"
						: $this->getModuleLocation($this->module);

				if (!file_exists($location . "/Model/$model.php")) throw new Exception('Модель не найдена.');
				$this->db; //FIXME грязный хак для того чтобы сработал сеттер базы данных. Потому что иногда его здесь еще нет, а для инициализаци модели используется адаптер базы данных по умолчанию
				require_once($location . "/Model/$model.php");
				$v = $this->{$k} = new $model();
			}
            elseif ($k === 'moduleConfig') {
                $module_loc = $this->getModuleLocation($this->module);
                $conf_file  = "{$module_loc}/conf.ini";
                if (is_file($conf_file)) {
                    $config_glob  = new Zend_Config_Ini(DOC_ROOT . 'conf.ini');
                    $extends_glob = $config_glob->getExtends();

                    $config_mod  = new Zend_Config_Ini($conf_file);
                    $extends_mod = $config_mod->getExtends();
                    $section_mod = ! empty($_SERVER['SERVER_NAME']) &&
                                   array_key_exists($_SERVER['SERVER_NAME'], $extends_mod) &&
                                   array_key_exists($_SERVER['SERVER_NAME'], $extends_glob)
                        ? $_SERVER['SERVER_NAME']
                        : 'production';

                    $config_mod = new Zend_Config_Ini($conf_file, $section_mod, true);

                    $conf_ext = $module_loc . "/conf.ext.ini";
                    if (file_exists($conf_ext)) {
                        $config_mod_ext  = new Zend_Config_Ini($conf_ext);
                        $extends_mod_ext = $config_mod_ext->getExtends();

                        $section_ext = ! empty($_SERVER['SERVER_NAME']) &&
                                       array_key_exists($_SERVER['SERVER_NAME'], $extends_glob) &&
                                       array_key_exists($_SERVER['SERVER_NAME'], $extends_mod_ext)
                                ? $_SERVER['SERVER_NAME']
                                : 'production';
                        $config_mod->merge(new Zend_Config_Ini($conf_ext, $section_ext));
                    }


                    $config_mod->setReadOnly();
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
