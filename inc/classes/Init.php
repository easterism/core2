<?php
header('Content-Type: text/html; charset=utf-8');

// Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
define("DOC_ROOT", dirname(str_replace("//", "/", $_SERVER['SCRIPT_FILENAME'])) . "/");
define("DOC_PATH", substr(DOC_ROOT, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))) ? : '/');

$autoload = __DIR__ . "/../../vendor/autoload.php";
if (!file_exists($autoload)) {
    \Core2\Error::Exception("Composer autoload is missing.");
}

require_once($autoload);
require_once("Error.php");

if ( ! empty($_SERVER['REQUEST_URI'])) {
    $f = explode(".", basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if (!empty($f[1]) && in_array($f[1], ['txt', 'js', 'css', 'env'])) {
        \Core2\Error::Exception("File not found", 404);
        die;
    }
}

require_once("Log.php");
require_once("Theme.php");
require_once 'Registry.php';
require_once 'Config.php';
require_once("HttpException.php");

use Laminas\Session\Config\SessionConfig;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\SessionStorage;
use Laminas\Session\SaveHandler\Cache AS SessionHandlerCache;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\Validator\HttpUserAgent;
use Laminas\Cache\Storage;
use Core2\Acl;
use Core2\Db;
use Core2\I18n;
use Core2\Login;
use Core2\Registry;
use Core2\Tool;
use Core2\Error;
use Core2\HttpException;
use Core2\Theme;


$conf_file = DOC_ROOT . "conf.ini";

if (!file_exists($conf_file)) {
    Error::Exception("conf.ini is missing.", 404);
}
$config = [
    'system'       => ['name' => 'CORE2'],
    'include_path' => '',
    'temp'         => getenv('TMP'),
    'debug'        => ['on' => false],
    'session'      => [
        'cookie_httponly'  => true,
        'use_only_cookies' => true,
    ],
    'database' => [
        'adapter' => 'Pdo_Mysql',
        'params'  => [
            'charset' => 'utf8',
            'driver_options'=> [
                PDO::ATTR_TIMEOUT => 5,
//                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ],
            'options' => [
                'caseFolding'                => false,
                'autoQuoteIdentifiers'       => true,
                'allowSerialization'         => true,
                'autoReconnectOnUnserialize' => true
            ]
        ],
        'isDefaultTableAdapter' => true,
        'profiler'              => [
            'enabled' => false,
            'class'   => 'Zend_Db_Profiler_Firebug',
        ]
    ],
];
// определяем путь к темповой папке
if (empty($config['temp'])) {
    $config['temp'] = sys_get_temp_dir();
    if (empty($config['temp'])) {
        $config['temp'] = "/tmp";
    }
}

//обрабатываем общий конфиг
try {

    if (PHP_SAPI === 'cli') { //определяем имя секции для cli режима
        $options = getopt('m:a:p:s:', array(
            'module:',
            'action:',
            'param:',
            'section:'
        ));
        if (( ! empty($options['section']) && is_string($options['section'])) || ( ! empty($options['s']) && is_string($options['s']))) {
            $_SERVER['SERVER_NAME'] = ! empty($options['section']) ? $options['section'] : $options['s'];
        }
    }

    $section = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';

    $conf     = new Core2\Config($config);
    $config   = $conf->getData()->merge($conf->readIni($conf_file, $section));


    $conf_d = DOC_ROOT . "conf.ext.ini";
    if (file_exists($conf_d)) {
        $config->merge($conf->readIni($conf_d, $section));
    }

}
catch (Exception $e) {
    Error::Exception($e->getMessage());
}

// отладка приложения
if ($config->debug->on) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

//проверяем настройки для базы данных
if ($config->database) {
    if ($config->database->adapter === 'Pdo_Mysql') {
        $config->database->params->adapterNamespace = 'Core_Db_Adapter';
        //подключаем собственный адаптер базы данных
        require_once($config->database->params->adapterNamespace . "_{$config->database->adapter}.php");
    } elseif ($config->database->adapter === 'Pdo_Pgsql') {
        $config->database->params->adapterNamespace = 'Zend_Db_Adapter';
        $config->database->schema = $config->database->params->dbname;
        $config->database->params->dbname = $config->database->pgname ? $config->database->pgname : 'postgres';
    }
    if (empty($config->database->params->dbname)) {
        Error::Exception('No database found!');
    }
}


//конфиг стал только для чтения
$config->setReadOnly();


if (isset($config->include_path) && $config->include_path) {
    set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
}

//подключаем мультиязычность
require_once 'I18n.php';
$translate = new I18n($config);

//сохраняем конфиг
Registry::set('config', $config);

//обрабатываем конфиг ядра
$core_conf_file = __DIR__ . "/../../conf.ini";
if (file_exists($core_conf_file)) {
    $config = new Core2\Config();
    Registry::set('core_config', $config->readIni($core_conf_file, 'production'));
}

require_once 'Db.php';
require_once 'Common.php';
require_once 'Templater2.php'; //DEPRECATED
require_once 'Templater3.php';
require_once 'SSE.php';
require_once 'Cli.php';


/**
 * Class Init
 * @property Modules $dataModules
 */
class Init extends Db {

        /**
         * @var StdClass|Zend_Session_Namespace
         */
        private $auth;

        /**
         * @var Core2\Acl
         */
        private $acl;
        protected $is_cli = false;
        protected $is_rest = array();
        protected $is_soap = array();
        private $is_xajax;

        private $route;

        /**
         * Init constructor.
         */
		public function __construct() {

			parent::__construct();

			if (empty($_SERVER['HTTPS'])) {
				if (isset($this->config->system) && ! empty($this->config->system->https)) {
					header('Location: https://' . $_SERVER['SERVER_NAME']);
				}
			}

			$tz = $this->config->system->timezone;
			if (!empty($tz)) {
				date_default_timezone_set($tz);
			}
		}


        /**
         * Общая проверка аутентификации
         */
        public function checkAuth() {

            // проверяем, есть ли в запросе токен авторизации
            $auth = $this->checkToken();
            if ($auth) { //произошла авторизация по токену
                $this->auth = $auth;
                Registry::set('auth', $this->auth);
                return; //выходим, если авторизация состоялась
            }

            if (PHP_SAPI === 'cli') { //TODO авторизация тоже не помешала бы
                $this->is_cli = true;
                Registry::set('auth', new StdClass());
                return;
            }

            //сохраняем параметры сессии
            if ($this->config->session) {
                $sess_config = new SessionConfig();
                $sess_config->setOptions($this->config->session);
                $sess_manager = new SessionManager($sess_config);
                //$sess_manager->setStorage(new SessionStorage());

                $sess_manager->getValidatorChain()->attach('session.validate', [new HttpUserAgent(), 'isValid']);
                if ($this->config->session->phpSaveHandler) {
                    $options = ['namespace' => $_SERVER['SERVER_NAME'] . ":Session"];
                    if ($this->config->session->remember_me_seconds) $options['ttl'] = $this->config->session->remember_me_seconds;
                    if ($this->config->session->savePath) $options['server'] = $this->config->session->savePath;

                    if ($this->config->session->saveHandler === 'memcached') {
                        $adapter = new Storage\Adapter\Memcached($options);
                        $sess_manager->setSaveHandler(new SessionHandlerCache($adapter));
                    } elseif ($this->config->session->phpSaveHandler === 'redis') {
                        $adapter = new Storage\Adapter\Redis($options);
//                        $sess_manager->getStorage()->markImmutable();
                        $sess_manager->setSaveHandler(new SessionHandlerCache($adapter));
                    }
                }

                //сохраняем менеджер сессий
                SessionContainer::setDefaultManager($sess_manager);

                $auth = new SessionContainer('Auth');
                if (!empty($auth->ID) && is_int($auth->ID)) {
                    if (!$auth->getManager()->isValid()) {
                        $this->closeSession('Y');
                    }
                    //is user active right now
                    if ($auth->ID == -1) { //это root
                        $this->auth = $auth;
                        Registry::set('auth', $this->auth);
                    }
                    if ($this->isUserActive($auth->ID) && isset($auth->accept_answer) && $auth->accept_answer === true) {
                        if ($auth->LIVEID) {
                            $row = $this->dataSession->find($auth->LIVEID)->current();
                            if (isset($row->is_kicked_sw) && $row->is_kicked_sw == 'Y') {
                                $this->closeSession();
                            }
                        }
                        $sLife = $this->getSetting('session_lifetime');
                        if ($sLife) {
                            $auth->setExpirationSeconds($sLife, "accept_answer");
                        }
                        $this->auth = $auth;
                        Registry::set('auth', $this->auth);
                    } else {
                        $this->closeSession('Y');
                    }

                }
            }
        }


        /**
         * The main dispatcher
         *
         * @return mixed|string
         * @throws Exception
         */
        public function dispatch() {

            if ($this->is_cli || PHP_SAPI === 'cli') {
                $cli = new \Core2\Cli();
                return $cli->run();
            }

            // Парсим маршрут
            $route = $this->routeParse();
            if (isset($route['api']) && !$this->auth) {
                header('HTTP/1.1 401 Unauthorized');
                $core_config = Registry::get('core_config');
                if ($core_config->auth && $core_config->auth->scheme == 'basic') {
                    header("WWW-Authenticate: Basic realm={$core_config->auth->basic->realm}, charset=\"UTF-8\"");
                }
                return;
            }

            if (!empty($_POST)) {
                //может ли xajax обработать запрос
                $xajax = new xajax();
                if ($xajax->canProcessRequest()) {
                    $this->is_xajax = $xajax;
                }
            }

            if (!$this->is_xajax) {
                $this->detectWebService();

                // Веб-сервис (REST)
                if ($matches = $this->is_rest) {
                    $this->setContext('webservice');

                    $this->checkWebservice();

                    require_once __DIR__ . "/../../inc/Interfaces/Delete.php"; //FIXME delete me
                    $webservice_controller = new ModWebserviceController();

                    $route['version'] = $matches['version'];

                    if (!empty($matches['module'])) {
                        $route['module'] = $matches['module'];
                        $route['action'] = $matches['action'];
                    }

                    return $webservice_controller->dispatchRest($route);

                }

                // Веб-сервис (SOAP)
                if ($matches = $this->is_soap) {
                    $this->setContext('webservice');
                    $this->checkWebservice();

                    $webservice_controller = new ModWebserviceController();

                    $version = $matches['version'];
                    $action = $matches['action'] == 'service.php' ? 'server' : 'wsdl';
                    $module_name = $matches['module'];

                    return $webservice_controller->dispatchSoap($module_name, $action, $version);
                }
            }

            if (!empty($this->auth->ID) && !empty($this->auth->NAME) && is_int($this->auth->ID)) {

                if (isset($route['module'])) {
                    if (isset($route['api']) && $route['api'] === 'swagger') {
                        if ($route['action'] == 'core2.html') {
                            //генерация свагера для общего API
                            require_once "OpenApiSpec.php";
                            $schema = new \Core2\OpenApiSpec();
                            $html = $schema->render();
                            header("Cache-Control: no-cache");
                            return $html;
                        }
                    }
                    elseif ($route['module'] === 'sse') {

                        require_once 'core2/inc/Interfaces/Event.php';

                        $this->setContext("admin", "sse");
                        session_write_close();
                        header("Content-Type: text/event-stream; charset=utf-8");
                        header("X-Accel-Buffering: no");
                        header("Cache-Control: no-cache");

                        $sse = new Core2\SSE();
                        while (1) {

                            $sse->loop();

                            if (connection_aborted()) break;

                            sleep(1);
                        }
                        return;
                    }

                }

                // LOG USER ACTIVITY
                $logExclude = array(
                    'profile/index/unread', //Запросы на проверку не прочитанных сообщений не будут попадать в журнал запросов
                );

                $this->logActivity($logExclude);
                //TODO CHECK DIRECT REQUESTS except iframes

                require_once 'Zend_Session_Namespace.php'; //DEPRECATED
                require_once 'core2/inc/classes/Acl.php';
                require_once 'core2/inc/Interfaces/Delete.php';
                require_once 'core2/inc/Interfaces/File.php';
                require_once 'core2/inc/Interfaces/Subscribe.php';
                require_once 'core2/inc/Interfaces/Switches.php';

                $this->acl = new Acl();
                $this->acl->setupAcl();

                if ($you_need_to_pay = $this->checkBilling()) return $you_need_to_pay;

                if ($this->is_xajax) {
                    //может ли xajax обработать запрос
                    $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
                    $xajax->processRequest();
                    return;
                }

            }
            else {
                require_once 'Login.php';

                $login = new Login();
                $login->setSystemName($this->getSystemName());
                $login->setFavicon($this->getSystemFavicon());
                $this->setupSkin();
                parse_str($route['query'], $request);
                $response = $login->dispatch($request); //TODO переделать на API
                if (!$response) {
                    //Immutable блокирует запись сессии
                    //SessionContainer::getDefaultManager()->getStorage()->markImmutable();
                    $response = $login->getPageLogin();
                    $blockNamespace = new SessionContainer('Block');
                    if (empty($blockNamespace->blocked)) {
                        SessionContainer::getDefaultManager()->destroy();
                    }
                }
                return $response;
            }

            //$requestDir = str_replace("\\", "/", dirname($_SERVER['REQUEST_URI']));

            if (
                empty($_GET['module']) && empty($route['api']) &&
                ($_SERVER['REQUEST_URI'] == $_SERVER['SCRIPT_NAME'] ||
                trim($_SERVER['REQUEST_URI'], '/') == trim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'])), '/'))
            ) {
                require_once 'Navigation.php';

                if (empty($this->auth->init)) { //нет сессии на сервере
                    return $this->getMenuMobile();
                }
                $this->setupSkin();
                if (!defined('THEME')) return;
                return $this->getMenu();
            }
            else {

                if ($this->deleteAction()) return '';
                if ($this->switchAction()) return '';

                $module = !empty($route['api']) ? $route['api'] : $route['module'];
                $extension = strrpos($module, '.') ? substr($module, strrpos($module, '.')) : null;
                if ($extension) $module = substr($module, 0, strrpos($module, '.'));
                if ($module == 'index') $module = "admin";
                if (empty($route['api']) && !empty($_GET['module'])) {
                    $module = $_GET['module'];
                }

                if (!$module) throw new Exception($this->translate->tr("Модуль не найден"), 404);
                $action = $route['action'];
                $this->setContext($module, $action);

                if ($this->fileAction()) return '';

                $this->setupSkin();
                if ($module === 'admin') {

                    if ($this->auth->MOBILE) {
                        require_once 'core2/inc/MobileController.php';
                        $core = new MobileController();
                    } else {
                        require_once 'core2/inc/CoreController.php';
                        $core = new CoreController();
                    }
                    if (empty($_GET['action'])) {
                        $_GET['action'] = 'index';
                    }
                    $action = "action_" . $action;
                    if (method_exists($core, $action)) {
                        return $core->$action();
                    } else {
                        throw new Exception(sprintf($this->translate->tr("Модуль %s не существует"), $action), 404);
                    }

                }
                else {
                    if ($action == 'index') {
                        $_GET['action'] = "index";

                        if ( ! $this->isModuleActive($module)) {
                            if (!empty($route['api'])) return Error::catchJsonException(sprintf($this->translate->tr("Модуль %s не существует"), $action), 404);
                            throw new Exception(sprintf($this->translate->tr("Модуль %s не существует"), $module), 404);
                        }

                        if ( ! $this->acl->checkAcl($module, 'access')) {
                            if (!empty($route['api'])) return Error::catchJsonException(sprintf($this->translate->tr("Доступ закрыт!"), $action), 403);
                            throw new Exception(911);
                        }
                    }
                    else {
                        $submodule_id = $module . '_' . $action;
                        if ( ! $this->isModuleActive($submodule_id)) {
                            if (!empty($route['api'])) return Error::catchJsonException(sprintf($this->translate->tr("Субмодуль %s не существует"), $action), 404);
                            throw new Exception(sprintf($this->translate->tr("Субмодуль %s не существует"), $action), 404);
                        }
                        $mods = $this->getSubModule($submodule_id);

                        //TODO перенести проверку субмодуля в контроллер модуля
                        if ($mods['sm_id'] && !$this->acl->checkAcl($submodule_id, 'access')) {
                            if (!empty($route['api'])) return Error::catchJsonException(sprintf($this->translate->tr("Доступ закрыт!"), $action), 403);
                            throw new Exception(911);
                        }
                    }

                    if (empty($mods['sm_path'])) {
                        $location = $this->getModuleLocation($module); //определяем местоположение модуля
                        if ($extension == ".sw") {
                            //модуль хочет serviceWorker
                            if (file_exists($location . "/serviceWorker.js")) {
                                header("Pragma: public");
                                header("Content-Type: text/javascript");
                                header("Content-length: " . filesize($location . "/serviceWorker.js"));
                                readfile($location . "/serviceWorker.js");
                                die;
                            } else {
                                Error::Exception("File not found", 404);
                            }
                        }
                        if ($this->translate->isSetup()) {
                            $this->translate->setupExtra($location, $module);
                        }
                        if (!empty($this->auth->MOBILE)) {
                            $modController = "Mobile" . ucfirst(strtolower($module)) . "Controller";
                        }
                        elseif (!empty($route['api'])) {
                            //запрос от приложения
                            header('Content-type: application/json; charset="utf-8"');
                            try {
                                $modController = "Mod" . ucfirst(strtolower($module)) . "Api";
                                $this->requireController($location, $modController);
                                $modController = new $modController();
                                $action = "action_" . $action;
                                if (method_exists($modController, $action)) {
                                    $out = $modController->$action();
                                    if (is_array($out)) $out = json_encode($out);
                                    return $out;
                                } else {
                                    throw new BadMethodCallException(sprintf($this->translate->tr("Метод %s не существует"), $action), 404);
                                }
                            } catch (HttpException $e) {
                                return Error::catchJsonException([
                                        'msg' => $e->getMessage(),
                                        'code' => $e->getErrorCode()
                                    ], $e->getCode() ?: 500);

                            } catch (\Exception $e) {
                                return Error::catchJsonException($e->getMessage(), $e->getCode());
                            }
                        }
                        else {
                            $modController = "Mod" . ucfirst(strtolower($module)) . "Controller";
                        }

                        $this->requireController($location, $modController);
                        $modController = new $modController();
                        $action = "action_" . $action;
                        if (method_exists($modController, $action)) {
                            $out = $modController->$action();
                            return $out;
                        } else {
                            throw new BadMethodCallException(sprintf($this->translate->tr("Метод %s не существует"), $action), 404);
                        }
                    } else {
                        return "<script>loadExt('{$mods['sm_path']}')</script>";
                    }
                }
            }
            return '';
        }


        /**
         *
         */
        public function __destruct() {

            if ($this->config &&
                $this->config->system &&
                $this->config->system->profile &&
                $this->config->system->profile->on
            ) {
                $log = new \Core2\Log('profile');

                if ($log->getWriter()) {
                    $sql_queries = $this->db->fetchAll("show profiles");
                $connection_id = $this->db->fetchOne("SELECT CONNECTION_ID()");
                    $total_time  = 0;
                    $max_slow    = [];

                    if ( ! empty($sql_queries)) {
                        foreach ($sql_queries as $k => $sql_query) {

                            if ( ! empty($sql_query['Duration'])) {
                                $total_time += $sql_query['Duration'];

                                if (empty($max_slow['Duration']) || $max_slow['Duration'] < $sql_query['Duration']) {
                                    $max_slow = $sql_query;
                                }
                            }
                        }
                    }

                $request_method = PHP_SAPI === 'cli'
                    ? 'CLI'
                    : ( ! empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'none');

                $query_string = PHP_SAPI === 'cli'
                    ? ($this->getPidCommand(posix_getpid()) ?: '-')
                    : ( ! empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');

                    if ($total_time >= 1 || count($sql_queries) >= 100 || count($sql_queries) == 0) {
                        $function_log = 'warning';
                    } else {
                        $function_log = 'info';
                    }


                $log->{$function_log}('request', [
                    'method'        => $request_method,
                    'time'          => round($total_time, 5),
                    'count'         => count($sql_queries),
                    'connection_id' => $connection_id,
                    'request'       => $query_string,
                    'max_slow'      => $max_slow,
                    'queries'       => $sql_queries,
                ]);
            }
                }
            }


    /**
     * @param int $pid
     * @return string|null
     */
    private function getPidCommand(int $pid):? string {

        $output = [];
        $cmd    = sprintf("ps -p %d -o command", $pid);
        exec($cmd, $output);

        $result = null;

        if ( ! empty($output[1])) {
            $line = $output[1];

            if (preg_match("~(?P<command>.+)$~", $line, $matches)) {
                $result = $matches['command'];
            }
        }

        return $result;
        }


        /**
         * Направлен ли запрос к вебсервису
         * @todo прогнать через роутер
         */
        private function detectWebService() {

            if ($this->is_rest || $this->is_soap) {
                return;
            }

            if ( ! isset($_SERVER['REQUEST_URI'])) {
                return;
            }

            $matches = [];
            if (preg_match('~api/v(?<version>\d+\.\d+)(?:/|)([^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
                $this->is_rest = [
                    'version' => $matches['version'],
                    'action'  => $matches[2]
                ];
                return;
            }

            // DEPRECATED
            if (preg_match('~api/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d+\.\d+)(?:/)(?<action>[^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
                $this->is_rest = $matches;
                return;
            }
            // DEPRECATED
            if (preg_match('~^(wsdl_([a-zA-Z0-9_]+)\.xml|ws_([a-zA-Z0-9_]+)\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {
                $this->is_soap = [
                    'module'  => ! empty($matches[2]) ? $matches[2] : $matches[3],
                    'version' => '',
                    'action'  => ! empty($matches[2]) ? 'wsdl.xml' : 'service.php',
                ];
                return;
            }
            if (preg_match('~soap/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d+\.\d+)/(?<action>wsdl\.xml|service\.php)~', $_SERVER['REQUEST_URI'], $matches)) {
                $this->is_soap = $matches;
                return;
            }
        }


        /**
         * Проверка наличия токена авторизации в запросе
         * @return StdClass|void
         */
        private function checkToken() {

            $token = '';
            if ( ! empty($_SERVER['HTTP_AUTHORIZATION'])) {
                if (strpos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer') === 0) {
                    $token = $_SERVER['HTTP_AUTHORIZATION'];
                    //TODO сделать поддержку других видов авторизации
                    if (!$token) return;
                    //TODO заменить модуль webservice на модуль auth
                    $this->setContext('webservice');
                    $this->checkWebservice();
                    try {
                        $webservice_api = new ModWebserviceApi();
                        //требуется webservice 2.6.0
                        return $webservice_api->dispatchToken($token);
                    } catch (HttpException $e) {
                        throw new \Exception(json_encode([
                            'msg' => $e->getMessage(),
                            'code' => $e->getErrorCode()
                        ]), $e->getCode() ?: 500);

                    } catch (\Exception $e) {
                        throw new \Exception($e->getMessage(), $e->getCode());
                    }
                }
                if (strpos($_SERVER['HTTP_AUTHORIZATION'], 'Basic') === 0) {
                    $core_config = Registry::get('core_config');
                    if ($core_config->auth && $core_config->auth->scheme == 'basic') {
                        //http basic auth allowed
                        try {
                            list($login, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
                            $user = $this->dataUsers->getUserByLogin($login);
                            if ($user && $user['u_pass'] === Tool::pass_salt(md5($password))) {
                                $auth = new \StdClass();

                                $auth->LIVEID = 0;

                                $auth->ID = (int)$user['u_id'];
                                $auth->NAME = $user['u_login'];
                                $auth->EMAIL = $user['email'];
                                $auth->LN = $user['lastname'];
                                $auth->FN = $user['firstname'];
                                $auth->MN = $user['middlename'];
                                $auth->ADMIN = $user['is_admin_sw'] == 'Y' ? true : false;
                                $auth->ROLE = $user['role'];
                                $auth->ROLEID = (int)$user['role_id'];
                                return $auth;
                            }
                        } catch (HttpException $e) {
                            throw new \Exception(json_encode([
                                'msg' => $e->getMessage(),
                                'code' => $e->getErrorCode()
                            ]), $e->getCode() ?: 500);

                        } catch (\Exception $e) {
                            throw new \Exception($e->getMessage(), $e->getCode());
                        }
                    }
                }
            }
            elseif ( ! empty($_SERVER['HTTP_CORE2M'])) {
                //DEPRECATED в будущих версиях авторизоваться с таким токеном будет нельзя
                if (strpos($_SERVER['HTTP_CORE2M'], 'Bearer') === 0) {
                    $token = $_SERVER['HTTP_CORE2M'];
                }
                if (!$token) return;
                $this->setContext('webservice');
                $this->checkWebservice();
                try {
                    $webservice_api = new ModWebserviceApi();
                    return $webservice_api->dispatchWebToken($token);
                } catch (HttpException $e) {
                    throw new \Exception(json_encode([
                        'msg' => $e->getMessage(),
                        'code' => $e->getErrorCode()
                    ]), $e->getCode() ?: 500);

                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), $e->getCode());
                }
            }
            elseif (!empty($_GET['apikey']) || !empty($_SERVER['HTTP_CORE2_APIKEY'])) {
                $apikey  = ! empty($_SERVER['HTTP_CORE2_APIKEY']) ? $_SERVER['HTTP_CORE2_APIKEY'] : $_GET['apikey'];
                //DEPRECATED ктото пытается авторизовать запрос при помощи api ключа
                // ключ проверим в webservice, если такой есть, то пропустим запрос, как если бы он авторизовался легальным способом
                $this->checkWebservice();
                try {
                    $webservice_api = new ModWebserviceApi();
                    return $webservice_api->dispatchApikey(trim($apikey));
                } catch (HttpException $e) {
                    throw new \Exception(json_encode([
                        'msg' => $e->getMessage(),
                        'code' => $e->getErrorCode()
                    ]), $e->getCode() ?: 500);

                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), $e->getCode());
                }
            }
        }


        /**
         * Проверка на наличие и работоспособности модуля Webservice
         */
        private function checkWebservice() {

            if ( ! $this->isModuleActive('webservice')) {
                throw new \Exception(json_encode([
                    'error_code'    => 'webservice_not_active',
                    'error_message' => $this->translate->tr('Модуль Webservice не активен')
                ]), 503);
            }

            $location = $this->getModuleLocation('webservice');
            $webservice_controller_path =  $location . '/ModWebserviceController.php';
            $webservice_controller_api  =  $location . '/ModWebserviceApi.php';

            if ( ! file_exists($webservice_controller_path) || ! file_exists($webservice_controller_api)) {
                throw new \Exception(json_encode([
                    'error_code'    => 'webservice_not_isset',
                    'error_message' => $this->translate->tr('Модуль Webservice не существует')
                ]), 500);
            }

            $autoload = $location . "/vendor/autoload.php";
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            require_once($webservice_controller_path);
            require_once($webservice_controller_api);

            if ( ! class_exists('ModWebserviceController')) {
                throw new \Exception(json_encode([
                    'error_code'    => 'webservice_broken',
                    'error_message' => $this->translate->tr('Модуль Webservice сломан')
                ]), 500);
            }
        }


        /**
         * Получение названия системы из conf.ini
         * @return mixed
         */
        private function getSystemName() {
            $res = $this->config->system->name;
            return $res;
        }

        /**
         * get favicons from conf.ini
         * @return array
         */
        private function getSystemFavicon() {

            $favicon_png = $this->config->system->favicon_png;
            $favicon_ico = $this->config->system->favicon_ico;

            $favicon_png = $favicon_png && is_file($favicon_png)
                ? $favicon_png
                : (is_file('favicon.png') ? 'favicon.png' : '');

            $favicon_ico = $favicon_ico && is_file($favicon_ico)
                ? $favicon_ico
                : (is_file('favicon.ico') ? 'favicon.ico' : '');

            if (defined('THEME')) {
                if (!$favicon_png) {
                    $favicon_png = 'core2/html/' . THEME . '/img/favicon.png';
                }
                if (!$favicon_ico) {
                    $favicon_ico = 'core2/html/' . THEME . '/img/favicon.ico';
                }
            }

            return [
                'png' => $favicon_png,
                'ico' => $favicon_ico,
            ];
        }

        /**
         * Проверка удаления с последующей переадресацией
         * Если запрос на удаление корректен, всегда должно возвращать true
         *
         * @return bool
         * @throws Exception
         */
        private function deleteAction() {

            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                return false;
            }

            parse_str($_SERVER['QUERY_STRING'], $params);

            if ( ! empty($params['res']) && ! empty($params['id'])) {
                header('Content-type: application/json; charset="utf-8"');
                $sess     = new SessionContainer('List');
                $resource = $params['res'];
                $sessData = $sess->$resource;
                $loc      = isset($sessData['loc']) ? $sessData['loc'] : '';

                if ( ! $loc) {
                    throw new Exception($this->translate->tr("Не удалось определить местоположение данных."), 13);
                }

                parse_str($loc, $temp);
                $this->setContext($temp['module']);

                if ($temp['module'] !== 'admin') {
                    $module        = $temp['module'];
                    $location      = $this->getModuleLocation($module); //определяем местоположение модуля
                    $modController = "Mod" . ucfirst(strtolower($module)) . "Controller";
                    $this->requireController($location, $modController);
                    $modController = new $modController();

                    if ($modController instanceof Delete) {
                        ob_start();
                        $res = $modController->action_delete($params['res'], $params['id']);
                        ob_clean();

                        if ($res) {
                            echo json_encode($res);
                            return true;
                        }
                    }
                }

                require_once 'core2/inc/CoreController.php';
                $core = new CoreController();
                echo json_encode($core->action_delete($params));

                return true;
            }

            return false;
        }


        /**
         * Метод выполнения переключений полей в таблицах (Y/N)
         * @return bool
         * @throws Exception
         */
        private function switchAction(): bool {

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return false;
            }

            parse_str($_SERVER['QUERY_STRING'], $params);

            if ( ! empty($params['module']) &&
                 ! empty($params['action']) &&
                 ! empty($params['loc']) &&
                 ! empty($params['resource']) &&
                 ! empty($_POST['data']) &&
                 ! empty($_POST['is_active']) &&
                 ! empty($_POST['value']) &&
                $params['module'] == 'admin' &&
                $params['action'] == 'switch' &&
                $params['loc'] == 'core'
            ) {

                $sess     = new SessionContainer('List');
                $sessData = $sess->{$params['resource']};
                $loc      = $sessData['loc'] ?? '';

                if ( ! $loc) {
                    throw new Exception($this->translate->tr("Не удалось определить местоположение данных."), 13);
                }

                parse_str($loc, $location_params);
                $this->setContext($location_params['module']);

                if ($location_params['module'] !== 'admin') {
                    $module        = $location_params['module'];
                    $location      = $this->getModuleLocation($module);
                    $modController = "Mod" . ucfirst(strtolower($module)) . "Controller";

                    $this->requireController($location, $modController);

                    $controller = new $modController();

                    if ($controller instanceof \Core2\Switches) {
                        try {
                            ob_start();
                            $result = $controller->action_switch($params['resource'], $_POST['data'], $_POST['value'], $_POST['is_active']);
                            ob_clean();
                        } catch (\Exception $e) {
                            $result = [ 'status' => $e->getMessage() ];
                        }

                        if ($result) {
                            header('Content-type: application/json; charset="utf-8"');
                            echo json_encode($result === true ? ['status' => "ok"] : $result);

                            return true;
                        }
                    }
                }

                require_once 'core2/inc/CoreController.php';
                $core = new CoreController();

                header('Content-type: application/json; charset="utf-8"');
                $core->action_switch();

                return true;
            }

            return false;
        }


        /**
         * Проверка наличия и целостности файла контроллера
         *
         * @param $location - путь до файла
         * @param $modController - название файла контроллера
         *
         * @throws Exception
         */
        private function requireController($location, $modController) {
            $controller_path = $location . "/" . $modController . ".php";
            if (!file_exists($controller_path)) {
                throw new Exception($this->translate->tr("Модуль не найден") . ": " . $modController, 404);
            }
            $autoload = $location . "/vendor/autoload.php";
            if (file_exists($autoload)) { //подключаем автозагрузку если есть
                require_once $autoload;
            }
            require_once $controller_path; // подлючаем контроллер
            if (!class_exists($modController)) {
                throw new Exception($this->translate->tr("Модуль сломан") . ": " . $location, 500);
            }
        }


        /**
         * Create the top menu
         * @return mixed|string
         * @throws Exception
         */
        private function getMenu() {

            $xajax = new xajax();
            $xajax->configure('javascript URI', 'core2/vendor/belhard/xajax');
            $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
//            $xajax->configure('errorHandler', true);

            if (Tool::isMobileBrowser()) {
                $tpl_file      = Theme::get("indexMobile");
                $tpl_file_menu = Theme::get("menuMobile");
            } else {
                $tpl_file      = Theme::get("index");
                $tpl_file_menu = Theme::get("menu");
            }

            $tpl      = new Templater3($tpl_file);
            $tpl_menu = new Templater3($tpl_file_menu);

            $tpl->assign('{system_name}', $this->getSystemName());

            $favicons = $this->getSystemFavicon();

            $tpl->assign('favicon.png', $favicons['png']);
            $tpl->assign('favicon.ico', $favicons['ico']);

            $tpl_menu->assign('<!--SYSTEM_NAME-->',        $this->getSystemName());
            $tpl_menu->assign('<!--CURRENT_USER_LOGIN-->', htmlspecialchars($this->auth->NAME));
            $tpl_menu->assign('<!--CURRENT_USER_FN-->',    $this->auth->FN ? htmlspecialchars($this->auth->FN) : "");
            $tpl_menu->assign('<!--CURRENT_USER_LN-->',    $this->auth->LN ? htmlspecialchars($this->auth->LN) : "");
            $tpl_menu->assign('[GRAVATAR_URL]',            "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth?->EMAIL ?? ''))));


            $modules_js     = [];
            $modules_css    = [];
            $navigate_items = [];
            $modules        = $this->getModuleList();

            foreach ($modules as $module) {
                if ( isset($module['sm_key'])) {
                    //пропускаем субмодули
                    continue;
                }

                $module_id = $module['module_id'];

                if ($module['is_public'] == 'Y') {
                    if ($module['isset_home_page'] == 'N') {
                        $first_action = 'index';

                        foreach ($modules as $mod) {
                            if ( ! empty($mod['sm_id']) && $module['m_id'] == $mod['m_id']) {
                                $first_action = $mod['sm_key'];
                                break;
                            }
                        }

                        $url           = "index.php?module={$module_id}&action={$first_action}";
                        $module_action = "&action={$first_action}";

                    } else {
                        $url           = "index.php?module=" . $module_id;
                        $module_action = '';
                    }

                    $tpl_menu->modules->assign('[MODULE_ID]',     $module_id);
                    $tpl_menu->modules->assign('[MODULE_NAME]',   $module['m_name']);
                    $tpl_menu->modules->assign('[MODULE_ACTION]', $module_action);
                    $tpl_menu->modules->assign('[MODULE_URL]',    $url);
                    $tpl_menu->modules->reassign();
                }


                if ($module_id == 'admin') {
                    continue;
                }

                try {
                    $location = $this->getModuleLocation($module_id); //получение расположения модуля
                    $modController = "Mod" . ucfirst($module_id) . "Controller";
                    $file_path = $location . "/" . $modController . ".php";

                    if (file_exists($file_path)) {
                        ob_start();
                        $autoload = $location . "/vendor/autoload.php";

                        if (file_exists($autoload)) {
                            require_once $autoload;
                        }

                        require_once $file_path;

                        // подключаем класс модуля
                        if (class_exists($modController)) {
                            $this->setContext($module_id);
                            $modController = new $modController();

                            if (($modController instanceof TopJs || method_exists($modController, 'topJs'))) {
                                $module_js_list = $modController->topJs();
                                if (is_array($module_js_list)) {
                                    foreach ($module_js_list as $val) {
                                        $module_js = Tool::addSrcHash($val);
                                        if (!in_array($module_js, $modules_js)) $modules_js[] = $module_js;
                                    }
                                }
                            }

                            if ($modController instanceof TopCss &&
                                $module_css_list = $modController->topCss()
                            ) {
                                foreach ($module_css_list as $val) {
                                    $module_css = Tool::addSrcHash($val);
                                    if (!in_array($module_css, $modules_css)) $modules_css[] = $module_css;
                                }
                            }

                            if (THEME !== 'default') {
                                $navi = new \Core2\Navigation(); //TODO переделать для обработки всех модулей сразу
                                $navi->setModuleNavigation($module['module_id']);
                                if ($modController instanceof Navigation) {
                                    $modController->navigationItems($navi);
                                }
                                $navigate_items[$module_id] = $navi->toArray();
                            }
                        }
                        ob_clean();
                    }
                } catch (\Exception $e) {
                    //проблемы с загрузкой модуля
                    //TODO добавить в log
                }
            }

            foreach ($modules as $module) {
                if ( ! empty($module['sm_key']) && $module['is_public'] === 'Y') {
                    $url = "index.php?module=" . $module['module_id'] . "&action=" . $module['sm_key'];

                    $tpl_menu->submodules->assign('[MODULE_ID]',      $module['module_id']);
                    $tpl_menu->submodules->assign('[SUBMODULE_ID]',   $module['sm_key']);
                    $tpl_menu->submodules->assign('[SUBMODULE_NAME]', $module['sm_name']);
                    $tpl_menu->submodules->assign('[SUBMODULE_URL]',  $url);
                    $tpl_menu->submodules->reassign();
                }
            }

            if ( ! empty($navigate_items)) {
                $navi = new \Core2\Navigation();
                foreach ($navigate_items as $module_name => $items) {
                    if ( ! empty($items)) {
                        foreach ($items as $item) {
                            $position = ! empty($item['position']) ? $item['position'] : '';

                            switch ($position) {
                                case 'profile':
                                    if ($tpl_menu->issetBlock('navigate_item_profile')) {
                                        $tpl_menu->navigate_item_profile->assign('[MODULE_NAME]', $module_name);
                                        $tpl_menu->navigate_item_profile->assign('[HTML]',        $navi->renderNavigateItem($item));
                                        $tpl_menu->navigate_item_profile->reassign();
                                    }
                                    break;

                                case 'main':
                                default:
                                    if ($tpl_menu->issetBlock('navigate_item')) {
                                        $tpl_menu->navigate_item->assign('[MODULE_NAME]', $module_name);
                                        $tpl_menu->navigate_item->assign('[HTML]',        $navi->renderNavigateItem($item));
                                        $tpl_menu->navigate_item->reassign();
                                    }
                                    break;
                            }
                        }
                    }
                }
            }


            if ( ! empty($this->config->system) &&
                 ! empty($this->config->system->theme) &&
                 ! empty($this->config->system->theme->bg_color) &&
                 ! empty($this->config->system->theme->text_color) &&
                 ! empty($this->config->system->theme->border_color) &&
                $tpl_menu->issetBlock('theme_style')
            ) {
                $tpl_menu->theme_style->assign("[BG_COLOR]",     $this->config->system->theme->bg_color);
                $tpl_menu->theme_style->assign("[TEXT_COLOR]",   $this->config->system->theme->text_color);
                $tpl_menu->theme_style->assign("[BORDER_COLOR]", $this->config->system->theme->border_color);
            }

            $tpl->assign('<!--index-->', $tpl_menu->render());
            $out = '';

            if ( ! empty($modules_css)) {
                foreach ($modules_css as $src) {
                    if ($src) $out .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
                }
            }

            if ( ! empty($modules_js)) {
                foreach ($modules_js as $src) {
                    if ($src) $out .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
                }
            }

            $tpl->assign('<!--xajax-->', "<script type=\"text/javascript\">var coreTheme  ='" . THEME . "'</script>" . $xajax->getJavascript() . $out);


            $system_js = "";
            if (isset($this->config->system->js) && is_object($this->config->system->js)) {
                foreach ($this->config->system->js as $src) {
                    $system_js .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
                }
            }
            $tpl->assign("<!--system_js-->", $system_js);

            $system_css = "";
            if (isset($this->config->system->css) && is_object($this->config->system->css)) {
                foreach ($this->config->system->css as $src) {
                    $system_css .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
                }
            }
            $tpl->assign("<!--system_css-->", $system_css);

            return $tpl->render();
        }



        /**
         * Получаем список доступных модулей
         * @return array
         */
        private function getModuleList() {
			$res  = $this->dataModules->getModuleList();
			$mods = array();
			$tmp  = array();
            foreach ($res as $data) {
				if (isset($tmp[$data['m_id']]) || $this->acl->checkAcl($data['module_id'], 'access')) {
                    //чтобы модуль отображался в меню, нужно еще людое из правил просмотри или чтения
                    $types = array(
                        'list_all',
                        'read_all',
                        'list_owner',
                        'read_owner',
                    );
                    $forMenu = false;
                    foreach ($types as $type) {
                        if ($this->acl->checkAcl($data['module_id'], $type)) {
                            $forMenu = true;
                            break;
                        }
                    }
                    if (!$forMenu) continue;
                    if ($data['sm_key']) {
                        if ($this->acl->checkAcl($data['module_id'] . '_' . $data['sm_key'], 'access')) {
                            $tmp[$data['m_id']][] = $data;
                        } else {
                            $tmp[$data['m_id']][] = array(
                                'm_id'            => $data['m_id'],
                                'm_name'          => $data['m_name'],
                                'module_id'       => $data['module_id'],
                                'isset_home_page' => empty($data['isset_home_page']) ? 'Y' : $data['isset_home_page'],
                                'is_public'       => $data['is_public']
                            );
                        }
                    } else {
                        $tmp[$data['m_id']][] = $data;
                    }
                }
            }
            unset($res);
            foreach ($tmp as $m_id => $data) {
                $module = current($data);
                $mods[] = array(
                    'm_id'            => $m_id,
                    'm_name'          => $module['m_name'],
                    'module_id'       => $module['module_id'],
                    'isset_home_page' => empty($module['isset_home_page']) ? 'Y' : $module['isset_home_page'],
                    'is_public'       => $module['is_public']
                );
                foreach ($data as $submodule) {
                    if (empty($submodule['sm_id'])) continue;
                    $mods[] = $submodule;
                }
            }
            if ($this->auth->ADMIN || $this->auth->NAME == 'root') {
                $tmp = array(
                    'm_id'            => -1,
                    'm_name'          => $this->translate->tr('Админ'),
                    'module_id'       => 'admin',
                    'isset_home_page' => 'Y',
                    'is_public'       => 'Y'
                );
                $mods[] = $tmp;
                $mods[] = array_merge($tmp, array('sm_id' => -1, 'sm_name' => $this->translate->tr('Модули'), 		'sm_key' => 'modules',    'loc' => 'core'));
                $mods[] = array_merge($tmp, array('sm_id' => -2, 'sm_name' => $this->translate->tr('Конфигурация'), 'sm_key' => 'settings',   'loc' => 'core'));
                $mods[] = array_merge($tmp, array('sm_id' => -3, 'sm_name' => $this->translate->tr('Справочники'),	'sm_key' => 'enum',       'loc' => 'core'));
                $mods[] = array_merge($tmp, array('sm_id' => -4, 'sm_name' => $this->translate->tr('Пользователи'), 'sm_key' => 'users',      'loc' => 'core'));
                $mods[] = array_merge($tmp, array('sm_id' => -5, 'sm_name' => $this->translate->tr('Роли'), 		'sm_key' => 'roles',      'loc' => 'core'));
                $mods[] = array_merge($tmp, array('sm_id' => -6, 'sm_name' => $this->translate->tr('Мониторинг'), 	'sm_key' => 'monitoring', 'loc' => 'core'));
                $mods[] = array_merge($tmp, array('sm_id' => -7, 'sm_name' => $this->translate->tr('Аудит'), 		'sm_key' => 'audit',      'loc' => 'core'));
            }
            return $mods;
        }




        /**
         * Основной роутер
         */
        private function routeParse() {
            $temp  = explode("/", DOC_PATH);
            $temp2 = explode("/", $_SERVER['REQUEST_URI']);
            foreach ($temp as $k => $v) {
            if (isset($temp2[$k]) && $temp2[$k] == $v) {
                    unset($temp2[$k]);
                }
            }
            reset($temp2);
            $api = false; //TODO переделать на $this->is_rest
            if (current($temp2) === 'api') {
                unset($temp2[key($temp2)]);
                $api = true;
            } //TODO do it for SOAP

            $route = array(
                'module'  => '',
                'action'  => 'index',
                'params'  => array(),
                'query'   => $_SERVER['QUERY_STRING']
            );

            $co = count($temp2);
            if ($co) {
                if ($co > 1) {
                    $i = 0;
                    //если мы здесь, значит хотим вызвать API
                    foreach ($temp2 as $k => $v) {
                        if ($i == 0) {
                            $route['api'] = strtolower($v);
                            $_GET['module'] = $route['api']; //DEPRECATED
                        }
                        elseif ($i == 1) {
                            if (!$v) $v = 'index';
                            $vv  = explode("?", $v);
                            $route['action'] = strtolower($vv[0]);
                        }
                        else {
                            if (!ceil($i%2)) {
                                $v = explode("?", $v);
                                if (isset($v[1])) {
                                    $route['params'][$v[0]] = '';
                                    $_GET[$v[0]] = ''; //DEPRECATED
                                } else {
                                    if (isset($temp2[$k + 1])) {
                                        $vv          = explode("?", $temp2[$k + 1]);
                                        $route['params'][$v[0]] = $vv[0];
                                        $_GET[$v[0]] = $vv[0]; //DEPRECATED

                                    } else {
                                        $route['params'][$v[0]] = '';
                                        $_GET[$v[0]] = ''; //DEPRECATED
                                    }
                                }
                            }
                        }
                        $i++;
                    }
                } else {
                    //в адресе нет глубины
                    $vv  = explode("?", current($temp2));
                    if (!empty($vv[1])) {
                        parse_str($vv[1], $_GET);
                    }
                    $route['module'] = $vv[0];
                    if (!$route['module'] || $route['module'] == 'index.php') { //DEPRECATED
                        // FIXME Убрать модуль и экшен по умолчанию
                        $route['module'] = !empty($_GET['module']) ? $_GET['module'] : 'admin';
                    }
                    $route['action'] = !empty($_GET['action']) ? $_GET['action'] : 'index';
                }
            }
            $this->route = $route;
            Registry::set('route', $route);
            return $route;
        }


        /**
         * Обрабатывает запросы к файлам
         *
         * @return bool
         * @throws Exception
         */
        private function fileAction() {
            if (!empty($_GET['module']) && !empty($_GET['filehandler'])) {
                $table = trim(strip_tags($_GET['filehandler']));
                if (!$table) throw new Exception($this->translate->tr("Ошибка запроса к файловому объекту"), 400);
                $context = '';
                $id = 0;
                if (isset($_GET['listid'])) {
                    $context = 'field_' . $_GET['f'];
                    $id = $_GET['listid'];
                } else {
                    if (!empty($_GET['fileid'])) {
                        $context = 'fileid';
                        $id      = $_GET['fileid'];
                    } elseif (!empty($_GET['thumbid'])) {
                        $context = 'thumbid';
                        $id      = $_GET['thumbid'];
                    } elseif (!empty($_GET['tfile'])) {
                        $context = 'tfile';
                        $id      = $_GET['tfile'];
                    }
                    if (!$id) throw new Exception(404);
                }

                if (!$context) throw new Exception($this->translate->tr("Не удалось определить контекст файла"), 400);

                if ($_GET['module'] !== 'admin') {
                    $module          = $_GET['module'];
                    $location        = $this->getModuleLocation($module); //определяем местоположение модуля
                    $modController   = "Mod" . ucfirst(strtolower($module)) . "Controller";
                    $this->requireController($location, $modController);
                    $modController  = new $modController();
                    if ($modController instanceof File) {
                        $res = $modController->action_filehandler($context, $table, $id);
                        if ($res) {
                            return true;
                        }
                    }
                }
                require_once 'core2/inc/CoreController.php';
                $core = new CoreController();
                $res = !empty($_GET['action']) ? $_GET['module'] . '_' . $_GET['action'] : $_GET['module'];
                return $core->fileHandler($res, $context, $table, $id);
            }
            return false;
        }


        /**
         * Список доступных модулей для core2m
         * @return string
         * @throws Exception
         */
        private function getMenuMobile() {

            header('Content-type: application/json; charset="utf-8"');

            $mods     = $this->getModuleList();
            $modsList = [];

            foreach ($mods as $data) {
                if ($data['is_public'] == 'Y') {
                    $modsList[$data['m_id']] = [
                        'module_id'  => $data['module_id'],
                        'm_name'     => strip_tags($data['m_name']),
                        'm_id'       => $data['m_id'],
                        'submodules' => []
                    ];
                }
            }
            foreach ($mods as $data) {
                if ( ! empty($data['sm_id']) && $data['is_public'] == 'Y') {
                    $modsList[$data['m_id']]['submodules'][] = [
                        'sm_id'   => $data['sm_id'],
                        'sm_key'  => $data['sm_key'],
                        'sm_name' => strip_tags($data['sm_name'])
                    ];
                }
            }

            //проверяем наличие контроллера для core2m в модулях
            foreach ($modsList as $k => $data) {
                $location      = $this->getModuleLocation($data['module_id']);
                if ($this->auth->MOBILE) { //признак того, что мы в core2m
                    $controller = "Mobile" . ucfirst(strtolower($data['module_id'])) . "Controller";
                } else {
                    $controller = "Mod" . ucfirst(strtolower($data['module_id'])) . "Api";
                }
                if ( ! file_exists($location . "/$controller.php")) {
                    unset($modsList[$k]); //FIXME если это не выполнится, core2m не будет работать!
                } else {
                    require_once $location . "/$controller.php";
                    $r = new \ReflectionClass($controller);
                    $submodules = []; //должен быть массивом!
                    foreach ($data['submodules'] as $s => $submodule) {
                        $method = 'action_' . $submodule['sm_key'];
                        if (!$r->hasMethod($method)) continue;
                        $submodules[] = $submodule;
                    }
                    $modsList[$k]['submodules'] = $submodules;
                }
            }
            $data = [
                'system_name' => strip_tags($this->getSystemName()),
                'id'          => $this->auth->ID,
                'name'        => $this->auth->LN . ' ' . $this->auth->FN . ' ' . $this->auth->MN,
                'login'       => $this->auth->NAME,
                'avatar'      => "https://www.gravatar.com/avatar/" . ($this->auth->EMAIL ? md5(strtolower(trim($this->auth->EMAIL))) : ''),
                'required_location' => false,
                'modules'     => $modsList
            ];
            if ($this->config->mobile) { //Настройки для Core2m
                if ($this->config->mobile->required && $this->config->mobile->required->location) {
                    $data['required_location'] = true; //требовать геолокацию для работы
                }
            }
            return json_encode($data);
//            return json_encode([
//                'status' => 'success',
//                'data'   => $data,
//            ] + $data); // Для совместимости с разными приложениями
        }


        /**
         * Установка контекста выполнения скрипта
         * @param string $module
         * @param string $action
         */
        private function setContext($module, $action = 'index') {
            $registry     = Registry::getInstance();
            //$registry 	= new ServiceManager();
            //$registry->setAllowOverride(true);
            $registry->set('context', array($module, $action));
        }

        /**
         * устанавливаем шкурку
         * @return void
         */
        private function setupSkin()
        {
            if ( ! empty($this->config->theme)) {
                define('THEME', $this->config->theme);

            } elseif ( ! empty($this->config->system->theme) &&
                ! empty($this->config->system->theme->name)
            ) {
                define('THEME', $this->config->system->theme->name);
            }

            if (!defined('THEME')) define('THEME', 'default');

            $theme_model = __DIR__ . "/../../html/" . THEME . "/model.json";
            if (!file_exists($theme_model)) {
                Error::Exception("Theme '" . THEME . "' model does not exists.");
            }
            $tpls = file_get_contents($theme_model);
            Theme::setModel(THEME, $tpls);
        }


        /**
         * @return string
         * @throws Exception
         */
        private function checkBilling() {

            // НЕ проверять если это запрос на выход из системы
            if ( ! empty($_GET['module']) && $_GET['module'] == 'admin' && $_SERVER['REQUEST_METHOD'] == 'PUT') {
                parse_str(file_get_contents("php://input"), $put_vars);
                if ( ! empty($put_vars['exit'])) {
                    return '';
                }
            }


            // НЕ проверять если это запрос на выполнение платежной операции
            if ( ! empty($_GET['module']) &&
                 ! empty($_POST['system_name']) &&
                 ! empty($_POST['type_operation']) &&
                $_GET['module'] == 'billing'
            ) {
                $this->acl->allow($this->auth->ROLE, 'billing');
                return '';
            }

            if ($this->isModuleActive('billing')) {
                $this->setContext('billing');

                $billing_location  = $this->getModuleLocation('billing');
                $billing_page_path = $billing_location . '/classes/Billing_Disable.php';

                if ( ! file_exists($billing_page_path)) {
                    throw new Exception("File '{$billing_page_path}' does not exists");
                }

                require_once($billing_page_path);

                if ( ! class_exists('Billing_Disable')) {
                    throw new Exception($this->_("Class Billing_Disable does not exists"));
                }

                $billing_disable = new Billing_Disable();
                if ($billing_disable->isDisable()) {
                    return $billing_disable->getDisablePage();
                }
            }

            return '';
        }

    }


/**
 * Обработчик POST запросов от xajax
 *
 * @param string $func
 * @param string  $loc DEPRECATED
 * @param array  $data
 *
 * @return xajaxResponse
 * @throws Exception
 * @throws Zend_Exception
 */
function post($func, $loc, $data) {
    $route      = Registry::get('route');
    if ($loc) {
        parse_str($loc, $route);
        $route['query'] = $_SERVER['QUERY_STRING'];
    }
    $translate = Registry::get('translate');
    $res       = new xajaxResponse();

    if (empty($route['module'])) throw new Exception($translate->tr("Модуль не найден"), 404);

    $acl = new Acl();

    Registry::set('context', array($route['module'], $route['action']));

    if ($route['module'] == 'admin') {
        require_once __DIR__ . "/../../mod/admin/ModAjax.php";
        $auth = Registry::get('auth');
        if ( ! $auth->ADMIN) throw new Exception(911);

        $xajax = new ModAjax($res);
        if (method_exists($xajax, $func)) {
            if (!empty($data['class_refid'])) $xajax->setRefId((int) $data['class_refid']);
            $xajax->setupAcl();
            try {
                return $xajax->$func($data);
            } catch (Exception $e) {
                Error::catchXajax($e, $res);
            }
        } else {
            throw new BadMethodCallException($translate->tr("Метод не найден"), 60);
        }

    } else {
        if ($route['action'] == 'index') {
            if ( ! $acl->checkAcl($route['module'], 'access')) {
                throw new Exception(911);
            }
        } else {
            if ( ! $acl->checkAcl($route['module'] . '_' . $route['action'], 'access')) {
                throw new Exception(911);
            }
        }

        $location  = $acl->getModuleLocation($route['module']);
        $file_path = $location . "/ModAjax.php";

        if (file_exists($file_path)) {
            $autoload = $location . "/vendor/autoload.php";
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            require_once $file_path;
            $xajax = new ModAjax($res);
            $func = 'ax' . ucfirst($func);
            if (method_exists($xajax, $func)) {
                if (!empty($data['class_refid'])) $xajax->setRefId((int) $data['class_refid']);
                try {
                    parse_str($route['query'], $params);
                    $data['params'] = $params;
                    return $xajax->$func($data);
                } catch (Exception $e) {
                    Error::catchXajax($e, $res);
                }
            } else {
                throw new BadMethodCallException($translate->tr("Метод не найден"), 60);
            }
        }
    }

    return $res;
}