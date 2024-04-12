<?php
require_once 'Acl.php';

use Core2\Registry;

class CommonPlugin extends \Core2\Acl {

    private $_p = array();
    protected $module = '';
	
	/**
     * CommonPlugin constructor.
     */
	public function __construct() {
		parent::__construct();
	}
	/**
	 * @param string $k
	 * @return bool
	 */
	public function __isset($k) {
		return isset($this->_p[$k]);
	}

	/**
	 * @param string $mod
	 */
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
				$v = $this->{$k} = Registry::get('auth');
			}
            elseif ($k == 'config') {
				$v = $this->{$k} = Registry::get('config');
			}
            elseif ($k == 'acl') {
				$v = $this->{$k} = Registry::get('acl');
			}
            elseif ($k == 'modAdmin') {
                require_once(DOC_ROOT . 'core2/inc/CoreController.php');
                $v = $this->{$k} = new CoreController();
            }
			$v = $this->{$k} = $this;
		}

		return $v;
	}

	/**
	 * @param string $k
	 * @param mixed $v
	 * @return $this
	 */
	public function __set($k, $v) {
		$this->_p[$k] = $v;
		return $this;
	}


}
