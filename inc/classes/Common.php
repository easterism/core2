<?
require_once 'Acl.php';
require_once 'Emitter.php';


/**
 * Class Common
 * @property StdClass        $acl
 * @property Zend_Config_Ini $moduleConfig
 * @property CoreController  $modAdmin
 */
class Common extends \Core2\Acl {

	protected $module;
	protected $path;

    /**
     * @var StdClass
     */
	protected $auth;
	protected $actionURL;
	protected $resId;

    /**
     * @var Zend_Config_Ini
     */
	protected $config;
	private $_p = array();
	private $AR = array(
        'module',
        'action'
    );


    /**
     * Common constructor.
     */
	public function __construct() {

        $child_class_name = get_class($this);

        if ($child_class_name == 'CoreController') {
            $child_class_name = 'admin';
        } else {
            $child_class_name = preg_match('~^Mod[A-z0-9\_]+Controller$~', $child_class_name)
                ? substr($child_class_name, 3, -10)
                : '';
        }

		parent::__construct();
        $reg     = Zend_Registry::getInstance();
		$context = $reg->get('context');

        if ($child_class_name) {
            $this->module = strtolower($child_class_name);
            if (!$reg->isRegistered('invoker')) {
                $reg->set('invoker', $this->module);
            }

        } else {
			$this->module = ! empty($context[0]) ? $context[0] : '';
        }

        $this->path      = 'mod/' . $this->module . '/';
        $this->auth      = $reg->get('auth');
        $this->resId     = $this->module;
		$this->actionURL = "?module=" . $this->module;

		if ( ! empty($context[1]) && $context[1] !== 'index') {
			$this->resId     .= '_' . $context[1];
			$this->actionURL .= "&action=" . $context[1];
		}

		$this->config = $reg->get('config');
	}


    /**
     * @param string $k
     * @return bool
     */
	public function __isset($k) {
		return isset($this->_p[$k]);
	}


    /**
     * Ищет перевод для строки $str
     * @param string $str
     * @param string $module
     * @return string
     */
    public function _($str, $module = '') {

        $module = $module ?: $this->module;

        if ($module === 'admin') {
            $module = 'core2';
        }

        return $this->translate->tr($str, $module);
    }


    /**
     * @return mixed
     * @throws Zend_Exception
     */
    public function getInvoker() {
        return Zend_Registry::get('invoker');
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
		//исключение для герета базы или кеша, выполняется всегда
		if (in_array($k, ['db', 'cache', 'translate', 'log'])) {
			return parent::__get($k);
		}
		//геттер для модели
		if (strpos($k, 'data') === 0) {
			return parent::__get($k . "|" . $this->module);
		}

		$v = NULL;

		if (array_key_exists($k, $this->_p)) {
			$v = $this->_p[$k];
		} else {
			// Получение экземпляра класса для работы с правами пользователей
			if ($k == 'acl') {
				$v = $this->{$k} = Zend_Registry::getInstance()->get('acl');
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
			// Получение экземпляра контроллера указанного модуля
			elseif (strpos($k, 'mod') === 0) {
				$module = strtolower(substr($k, 3));

				if ($module === 'admin') {
					require_once(DOC_ROOT . 'core2/inc/CoreController.php');
					$v         = $this->modAdmin = new CoreController();
					$v->module = $module;
				} elseif ($location = $this->getModuleLocation($module)) {
					if (!$this->isModuleActive($module)) {
						throw new Exception("Модуль \"{$module}\" не активен");
					}

					$cl              = ucfirst($k) . 'Controller';
					$controller_file = $location . '/' . $cl . '.php';

					if (!file_exists($controller_file)) {
						throw new Exception(sprintf($this->translate->tr("Модуль \"%s\" сломан. Не найден файл контроллера."), $module));
					}

                    $autoload_file = $location . "/vendor/autoload.php";
                    if (file_exists($autoload_file)) {
                        require_once($autoload_file);
                    }

					require_once($controller_file);

					if (!class_exists($cl)) {
						throw new Exception(sprintf($this->translate->tr("Модуль \"%s\" сломан. Не найден класс контроллера."), $module));
					}



					$v         = $this->{$k} = new $cl();
					$v->module = $module;

				} else {
					throw new Exception(sprintf($this->translate->tr("Модуль \"%s\" не найден"), $module));
				}
			}

			// Получение экземпляра плагина для указанного модуля
			elseif (strpos($k, 'plugin') === 0) {
                $plugin = ucfirst(substr($k, 6));
                $module = $this->module;
                $location = $this->getModuleLocation($this->module);
                $plugin_file = "{$location}/Plugins/{$plugin}.php";
                if (!file_exists($plugin_file)) {
                    throw new Exception(sprintf($this->translate->tr("Плагин \"%s\" не найден."), $plugin));
                }
                require_once("CommonPlugin.php");
                require_once($plugin_file);
                $temp = "\\" . $module . "\\Plugins\\" . $plugin;
                $v = $this->{$k} = new $temp();
                $v->setModule($this->module);
            }

			// Получение экземпляра api класса указанного модуля
			elseif (strpos($k, 'api') === 0) {
                $module     = substr($k, 3);
                if ($k == 'api') $module = $this->module;
                if ($this->isModuleActive($module)) {
                    $location = $module == 'Admin'
                            ? DOC_ROOT . "core2/mod/admin"
                            : $this->getModuleLocation($module);
                    $module = ucfirst($module);
                    $module_api = "Mod{$module}Api";
                    if (!file_exists($location . "/{$module_api}.php")) {
                        return new stdObject();
                    } else {
                        require_once "CommonApi.php";
                        require_once $location . "/{$module_api}.php";
                        $api = new $module_api();
                        if (!is_subclass_of($api, 'CommonApi')) {
                            return new stdObject();
                        }
                        $v = $this->{$k} = $api;
                    }
                } else {
                    return new stdObject();
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

	
	/**
	 * Check if $r in available request. If no, unset request key
	 * @param array $r - key->value array
	 */
	protected function checkRequest(Array $r) {
		$r = array_merge($this->AR, $r); //TODO сдалать фильтр для запросов
		foreach ($_REQUEST as $k => $v) {
			if (!in_array($k, $r)) {
				unset($_REQUEST[$k]);
			}
		}
	}


	/**
	 * Print link to CSS file
	 * @param string $href - CSS filename
	 */
	protected function printCss($href) {
        Tool::printCss($href);
	}


	/**
	 * Print link to CSS file
     * @param string $module module name
	 * @param string $href   CSS filename
     * @throws \Exception
	 */
	protected function printCssModule($module, $href) {
        $src_mod = $this->getModuleLoc($module);
        Tool::printCss($src_mod . $href);
	}


	/**
	 * 
	 * Print link to JS file
	 * @param string $src - JS filename
	 * @param bool   $chachable
	 */
	protected function printJs($src, $chachable = false) {
        Tool::printJs($src, $chachable);
	}


	/**
	 * Print link to JS file
	 * @param string $module    module name
	 * @param string $src       JS filename
	 * @param bool   $chachable
     * @throws \Exception
	 */
	protected function printJsModule($module, $src, $chachable = false) {
		$src_mod = $this->getModuleLoc($module);
        Tool::printJs($src_mod . $src, $chachable);
	}


    /**
     * Порождает событие для модулей, реализующих интерфейс Subscribe
     * @param       $event_name
     * @param array $data
     * @return array
     */
	protected function emit($event_name, $data = []) {
	    $em = new \Core2\Emitter($this, $this->module);
        $em->addEvent($event_name, $data);
        return $em->emit();
    }


    /**
     * @param string          $message
     * @param array|Exception $data
     * @return bool
     */
    protected function sendErrorMessage($message, $data = []) {

        $admin_email = $this->getSetting('admin_email');

        if (empty($admin_email)) {
            return false;
        }

        $cabinet_name = ! empty($this->config->system) ? $this->config->system->name : 'Без названия';
        $cabinet_host = ! empty($this->config->system) ? $this->config->system->host : '';
        $protocol     = ! empty($this->config->system) && $this->config->system->https ? 'https' : 'http';


        $data_msg = '';

        if ($data) {
            if ($data instanceof Exception) {
                $data_msg .= $data->getMessage() . "<br>";
                $data_msg .= '<b>' . $data->getFile() . ': ' . $data->getLine() . "</b><br><br>";
                $data_msg .= '<pre>' . $data->getTraceAsString() . '</pre>';

            } else {
                $data_msg = '<pre>' . print_r($data, true) . '</pre>';
            }
        }

        $error_date = date('d.m.Y H:i:s');

        $body = "
            Ошибка в системе <a href=\"{$protocol}://{$cabinet_host}\">{$cabinet_host}</a><br><br>
            
            <small style=\"color:#777\">{$error_date}</small><br>
            <b>{$message}</b><br><br>        
            
            {$data_msg}
        ";


        $this->modAdmin->createEmail()
            ->to($admin_email)
            ->subject($cabinet_name . ': Ошибка')
            ->body($body)
            ->send(true);

        return true;
    }
}


/**
 * Class stdObject
 */
class stdObject {

    /**
     * @param string $method
     * @param array  $arguments
     * @return bool
     */
    public function __call($method, $arguments) {
        return false;
    }
}