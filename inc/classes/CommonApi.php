<?php
require_once 'Acl.php';
require_once 'Emitter.php';
require_once 'HttpException.php';
require_once 'Request.php';

use Core2\Registry;
use Core2\Error;
use Core2\Emitter;
use Core2\Request;
use Core2\Routing\Router;

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

    protected array $route = [];


    /**
     * CommonApi constructor.
     * @param string $module
     */
	public function __construct() {
        parent::__construct();
        $reg     = Registry::getInstance();

		$this->auth  = $reg->isRegistered('auth') ? $reg->get('auth') : null;
        $this->route = $reg->isRegistered('route') ? $reg->get('route') : [];
        if ($this->route && $this->route['query']) {
            parse_str($this->route['query'], $this->route['query']);
        }
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

        //исключение для гетера базы или кеша, выполняется всегда
        if (in_array($k, ['db', 'db2', 'cache', 'translate', 'log', 'core_config', 'fact'])) {
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
                    if (!$this->isModuleActive($module)) {
                        return new stdObject();
                    }
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
                Error::Exception($this->_("Не найден конфигурационный файл модуля."), 500);
            } else {
                $reg->set($k . "|" . $this->module, $module_config);
                return $module_config;
            }
        }
        elseif (strpos($k, 'mod') === 0) {
            throw new \Exception($this->_("ModController is no able to use in API"), 500);
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
        $request_raw = str_replace("\xEF\xBB\xBF", '', $request_raw);
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

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $request_raw = null;
                break;
            case 'POST':
                if (strpos($h['Content-Type'], 'multipart/form-data') === 0) {
                    $request_raw = $_POST;
                }
                else if (strpos($h['Content-Type'], 'application/x-www-form-urlencoded') === 0) {
                    $request_raw = $_POST;
                }
                else if (strpos($h['Content-Type'], 'application/json') === 0) {
                    $request_raw = json_decode($request_raw, true);
                    if (\JSON_ERROR_NONE !== json_last_error()) {
                        throw new \InvalidArgumentException(json_last_error_msg(), 400);
                    }
                }
                else {
                    throw new \Exception('Unsupported Media Type', 415);
                }
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                if (isset($h['Content-Type'])) {
                    if (strpos($h['Content-Type'], 'application/x-www-form-urlencoded') === 0) {
                        parse_str($request_raw, $request_raw);
                    } else if (strpos($h['Content-Type'], 'application/json') === 0) {
                        $request_raw = json_decode($request_raw, true);
                        if (\JSON_ERROR_NONE !== json_last_error()) {
                            throw new \InvalidArgumentException(json_last_error_msg(), 400);
                        }
                    }
                }
                break;
            default:
                throw new \Exception('method not handled', 405);
        }
        return $request_raw;
    }


    /**
     * Запуск метода из роутера
     * @param Router $router
     * @return mixed
     * @throws Exception
     */
    protected function runRouter(Router $router): mixed {

        $request = new Request();

        // Обнуление
        $_GET     = [];
        $_POST    = [];
        $_REQUEST = [];
        $_FILES   = [];
        $_COOKIE  = [];


        $route_method = $router->getRoute();

        if ( ! $route_method) {
            http_response_code(404);
            return [
                'error_code'    => 'method_not_found',
                'error_message' => "Метод не найден: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}"
            ];
        }

        try {
            return $route_method->run($request);

        } catch (\Core2\HttpException $e) {
            http_response_code($e->getCode());

            return [
                'error_code'    => $e->getErrorCode(),
                'error_message' => $e->getMessage()
            ];

        } catch (\Zend_Db_Exception $e) {
            $this->log->error("Database error", $e);
            $is_debug = $this->config?->debug?->on || $this->auth->ADMIN;

            http_response_code(500);
            return [
                'error_code'    => 'error',
                'error_message' => $is_debug ? $e->getMessage() : $this->_('Ошибка базы данных. Обновите страницу или попробуйте позже')
            ];

        } catch (\Exception $e) {
            $this->log->error("Fatal error", $e);
            $is_debug = $this->config?->debug?->on || $this->auth->ADMIN;

            http_response_code(500);
            return [
                'error_code'    => 'error',
                'error_message' => $is_debug ? $e->getMessage() : $this->_('Ошибка. Обновите страницу или попробуйте позже')
            ];
        }
    }
}
