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
        'action',
        'PHPSESSID'
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
     * @return Common|null|Zend_Db_Adapter_Abstract
     * @throws Exception
     */
    public function __get($k) {

		$v = NULL;
		//исключение для герета базы, выполняется всегда
		if ($k == 'db') {
			$reg = Zend_Registry::getInstance();
			$this->config = $reg->get('config');
			if (!$reg->isRegistered('db')) {
				$db = $this->establishConnection($this->config->database);
			} else {
				$db = $reg->get('db');
			}
			$v = $this->{$k} = $db;
			return $v;
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

        // Получение указанного кэша
		if ($k == 'cache') {
			if (array_key_exists($k, $this->_p)) {
				$v = $this->_p[$k];
			} else {
				$v = $this->{$k} = Zend_Cache::factory('Core',
					$this->backend,
					$this->frontendOptions,
					$this->backendOptions);
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

                } elseif ($this->isModuleActive($module)) {
                    $location = $this->getModuleLocation($module);
                    $cl       = ucfirst($k) . 'Controller';
                    require_once($location . '/' . $cl . '.php');
                    $v = $this->{$k} = new $cl();
                    $v->module = $module;

                } else {
                    throw new Exception('Запрашиваемый модуль не найден.');
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
		$r = array_merge($this->AR, $r);
		foreach ($_REQUEST as $k => $v) {
			if (!in_array($k, $r)) {
				unset($_REQUEST[$k]);
			}
		}
	}
	
	/**
	 * 
	 * Print link to CSS file
	 * @param string $href - CSS filename
	 */
	protected function printCss($href) {
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