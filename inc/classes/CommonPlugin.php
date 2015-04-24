<?
require_once 'Acl.php';

class CommonPlugin extends Acl {

    private $_p = array();
    protected $module = '';

	public function __construct() {
		parent::__construct();
	}

	public function __isset($k) {
		return isset($this->_p[$k]);
	}

    final public function setModule($mod)
    {
        $this->module = $mod;
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
			elseif ($k == 'auth') {
				$v = $this->{$k} = Zend_Registry::getInstance()->get('auth');
			}
            elseif ($k == 'config') {
				$v = $this->{$k} = Zend_Registry::getInstance()->get('config');
			}
            elseif ($k == 'acl') {
				$v = $this->{$k} = Zend_Registry::getInstance()->get('acl');
			}
            elseif ($k == 'modAdmin') {
                require_once(DOC_ROOT . 'core2/inc/CoreController.php');
                $v = $this->{$k} = new CoreController();
            }
			$v = $this->{$k} = $this;
		}

		return $v;
	}

	public function __set($k, $v) {
		$this->_p[$k] = $v;
		return $this;
	}


}
