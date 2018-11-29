<?
require_once 'Acl.php';


/**
 * Class CommonApi
 * @property StdClass       $acl
 * @property CoreController $modAdmin
 */
class CommonApi extends \Core2\Acl {

    /**
     * @var StdClass|SessionContainer
     */
	protected $auth;

    /**
     * @var Zend_Config_Ini
     */
	protected $config;

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
		$this->config = Zend_Registry::get('config');
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
