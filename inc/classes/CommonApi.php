<?php
require_once 'Acl.php';

use Core2\Registry;

/**
 * Class CommonApi
 * @property StdClass        $acl
 * @property CoreController  $modAdmin
 */
class CommonApi extends \Core2\Acl {

    /**
     * @var StdClass|SessionContainer
     */
	protected $auth;

    protected $module;


    /**
     * CommonApi constructor.
     * @param string $module
     */
	public function __construct() {
        $child_class_name = get_class($this);
        $mod_name = preg_match('~^Mod[A-z0-9\_]+(Api)$~', $child_class_name, $matches)
            ? substr($child_class_name, 3, -strlen($matches[1]))
            : '';
		parent::__construct();
        $reg     = Registry::getInstance();
        $this->module = strtolower($mod_name);
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
     * Автоматическое подключение других модулей
     * инстансы подключенных объектов хранятся в массиве $_p
     *
     * @param string $k
     * @return Common|null|Zend_Db_Adapter_Abstract|Zend_Config_Ini|CoreController|mixed
     * @throws Exception
     */
    public function __get($k) {
        $reg = Registry::getInstance();
        if ($reg->isRegistered($k)) { //для стандартных объектов
            return $reg->get($k);
        }
        if ($reg->isRegistered($k . "|")) { //подстараховка от случайной перезаписи ключа
            return $reg->get($k . "|");
        }

        //исключение для герета базы или кеша, выполняется всегда
        if (in_array($k, ['db', 'cache', 'translate', 'log', 'core_config', 'fact'])) {
            return parent::__get($k);
        }
        //геттер для модели
        if (strpos($k, 'data') === 0) {
            return parent::__get($k);
        }
        elseif (strpos($k, 'worker') === 0) {
            return parent::__get($k);
        }

		$v = NULL;


        if ($k == 'modAdmin') {
            require_once(DOC_ROOT . 'core2/inc/CoreController.php');
            $v = new CoreController();
        }
        elseif (strpos($k, 'api') === 0) {
            $module = substr($k, 3);

            $location = $module == 'Admin'
                ? DOC_ROOT . "core2/mod/admin"
                : $this->getModuleLocation($module);
            if ($location) {

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

                    $v = $api;
                }
            } else {
                return new stdObject();
            }
        }
        elseif ($k === 'moduleConfig') {
            $km = $k . "|" . $this->module;
            if ($reg->isRegistered($km)) {
                return $reg->get($km);
            }
            $module_config = $this->getModuleConfig($this->module);

            if ($module_config === false) {
                \Core2\Error::Exception($this->_("Не найден конфигурационный файл модуля."), 500);
            } else {
                $reg->set($k . "|" . $this->module, $module_config);
                return $module_config;
            }
        }
        else {
            $v = $this;
        }
        $reg->set($k . "|", $v);
		return $v;
	}

    /**
     * получени еданных из потока воода
     * @return array|false|mixed|string|string[]
     */
    public function getInputBody()
    {
        $request_raw = file_get_contents('php://input', 'r');
        $request_raw =  str_replace("\xEF\xBB\xBF", '', $request_raw);
        if ( ! function_exists('getallheaders')) {
            /**
             * @return array
             */
            function getallheaders() {
                $headers = [];
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                    }
                }
                return $headers;
            }
        }
        $h = getallheaders();
        if ($h['Content-Type'] == 'application/json') {
            $request_raw = json_decode($request_raw, true);
        }
        return $request_raw;
    }

}
