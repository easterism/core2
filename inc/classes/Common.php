<?
require_once 'Acl.php';

class Common extends Acl {
	protected $module;
	protected $path;
	protected $auth;
	protected $actionURL;
	protected $resId;
	protected $config;
	private $_p = array();
	private $AR = array(
        'module',
        'action'
    );
	
	public function __construct() {
		parent::__construct();
		$this->module = !empty($_GET['module']) ? $_GET['module'] : '';
		$this->path = 'mod/' . $this->module . '/';
		$this->auth = Zend_Registry::get('auth');
		$this->resId = $this->module;
		$this->actionURL = "?module=" . $this->module;
		if (!empty($_GET['action']) && $_GET['action'] != 'index') {
			$this->resId .= '_' . $_GET['action'];
			$this->actionURL .= "&action=" . $_GET['action'];
		}
		
		$this->config = Zend_Registry::get('config');
	}

	public function __isset($k) {
		return isset($this->_p[$k]);
	}


    /**
     * Автоматическое подключение других модулей
     * инстансы подключенных объектов хранятся в массиве $_p
     *
     * @param string $k
     * @return Common|null|Zend_Db_Adapter_Abstract|CoreController|mixed
     * @throws Exception
     */
    public function __get($k) {

		$v = NULL;
		if ($k == 'moduleConfig') {
			if (array_key_exists($k, $this->_p)) {
				$v = $this->_p[$k];
			} else {
				$conf_file = "mod/{$this->module}/conf.ini";
				if (is_file($conf_file)) {
					$configExt = new Zend_Config_Ini("conf.ini");
					$configMod = new Zend_Config_Ini($conf_file);
					$ext	    = $configExt->getExtends();
					$extMod    = $configMod->getExtends();
					if (!empty($_SERVER['SERVER_NAME']) && array_key_exists($_SERVER['SERVER_NAME'], $ext) && array_key_exists($_SERVER['SERVER_NAME'], $extMod)) {
						$modConfig = new Zend_Config_Ini($conf_file, $_SERVER['SERVER_NAME']);
					} else {
						$modConfig = new Zend_Config_Ini($conf_file, 'production');
					}
					$modConfig->setReadOnly();
					$v = $this->{$k} = $modConfig;
				} else {
					Error::Exception("Не найден конфигурационный файл модуля.", 500);
				}
			}
			return $v;
		}
		//исключение для герета базы или кеша, выполняется всегда
		if ($k == 'db' || $k == 'cache') {
			return parent::__get($k);
		}

        // Получение экземпляра класса для работы с правами пользователей
		if ($k == 'acl') {
			if (array_key_exists($k, $this->_p)) {
				$v = $this->_p[$k];
			} else {
				$v = $this->{$k} = Zend_Registry::getInstance()->get('acl');
			}
			return $v;
		}

        // Получение экземпляра контроллера указанного модуля
		if (strpos($k, 'mod') === 0) {
			if (array_key_exists($k, $this->_p)) {
				$v = $this->_p[$k];
			} else {
				$module   = strtolower(substr($k, 3));

                if ($module == 'admin') {
                    require_once(DOC_ROOT . 'core2/inc/CoreController.php');
                    $v = $this->modAdmin = new CoreController();
                    $v->module = $module;

                } elseif ($location = $this->getModuleSrc($module)) {
                    if ( ! $this->isModuleActive($module)) {
                        throw new Exception("Модуль \"{$module}\" не активен");
                    }

                    $cl = ucfirst($k) . 'Controller';
                    $controller_path = DOC_ROOT . $location . '/' . $cl . '.php';

                    if ( ! file_exists($controller_path)) {
                        throw new Exception("Модуль \"{$module}\" сломан");
                    }

                    require_once($controller_path);

                    if ( ! class_exists($cl)) {
                        throw new Exception("Модуль \"{$module}\" сломан");
                    }

                    $v = $this->{$k} = new $cl();
                    $v->module = $module;

                } else {
                    throw new Exception("Модуль \"{$module}\" не найден");
                }
			}
			return $v;
		}

        // Получение экземпляра модели текущего модуля
		if (strpos($k, 'data') === 0) {
			if (array_key_exists($k, $this->_p)) {
				$v = $this->_p[$k];
			} else {
				$model    = substr($k, 4);
                $location = $this->module == 'admin'
                    ? "core2/mod/{$this->module}"
                    : $this->getModuleLocation($this->module);

                if (!file_exists($location . "/Model/$model.php")) throw new Exception('Модель не найдена.');
				$this->db; //FIXME грязный хак для того чтобы сработал сеттер базы данных. Потому что иногда его здесь еще нет, а для инициализаци модели используется адаптер базы данных по умолчанию
                require_once($location . "/Model/$model.php");
				$v = $this->{$k} = new $model();
			}
			return $v;
		}

		if (array_key_exists($k, $this->_p)) {
			$v = $this->_p[$k];
		} else {
			$v = $this->{$k} = $this;
		}

		return $v;
	}

	public function __set($k, $v) {
		$this->_p[$k] = $v;
		return $this;
	}

	
	/**
	 * 
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
        if (strpos($href, '?')) {
            $explode_href = explode('?', $href, 2);
            $href .= file_exists(DOC_ROOT . $explode_href[0])
                ? '&_=' . crc32(md5_file(DOC_ROOT . $explode_href[0]))
                : '';
        } else {
            $href .= file_exists(DOC_ROOT . $href)
                ? '?_=' . crc32(md5_file(DOC_ROOT . $href))
                : '';
        }
		echo '<link href="' . $href . '" type="text/css" rel="stylesheet" />';
	} 
	
	/**
	 * 
	 * Print link to JS file
	 * @param string $src - JS filename
	 */
	protected function printJs($src, $chachable = false) {
		if ($chachable) {
			//помещаем скрипт в head
			echo "<script type=\"text/javascript\">jsToHead('$src')</script>";
		} else {
			echo '<script type="text/javascript" language="JavaScript" src="' . $src . '"></script>';
		}
	} 
	
}