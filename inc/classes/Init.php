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
        exit(); // FIXME Нужно убрать
    }
}

require_once("Log.php");
require_once("Theme.php");
require_once 'Registry.php';
require_once 'Config.php';
require_once("Router.php");

use Laminas\Session\Config\SessionConfig;
use Laminas\Session\SessionManager;
use Laminas\Session\SaveHandler\Cache AS SessionHandlerCache;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\Validator\HttpUserAgent;
use Laminas\Cache\Storage;
use Core2\Acl;
use Core2\I18n;
use Core2\Login;
use Core2\Registry;
use Core2\Tool;
use Core2\Error;
use Core2\Theme;
use Core2\Router;


$conf_file = DOC_ROOT . "conf.ini";

if (!file_exists($conf_file)) {
    Error::Exception("conf.ini is missing.", 404);
}
$config_origin = [
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
if (empty($config_origin['temp'])) {
    $config_origin['temp'] = sys_get_temp_dir();
    if (empty($config_origin['temp'])) {
        $config_origin['temp'] = "/tmp";
    }
}

//обрабатываем общий конфиг
try {

    $section = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';

    $conf     = new Core2\Config($config_origin);
    $config   = $conf->getData()->merge($conf->readIni($conf_file, $section));


    $conf_d = __DIR__ . "/../../conf.ext.ini";
    if (file_exists($conf_d)) {
        $config->merge($conf->readIni($conf_d, $section));
    }

    if (empty($_SERVER['HTTPS'])) {
        if (isset($config->system) && ! empty($config->system->https)) {
            header('Location: https://' . $_SERVER['SERVER_NAME']);
            exit(); // TODO нужно убрать
        }
    }
    $tz = $config->system->timezone;
    if (!empty($tz)) {
        date_default_timezone_set($tz);
    }
    if (!$config) throw new Exception("Unable to load configuration.");
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

require_once 'Acl.php';
require_once 'Common.php';
require_once 'SSE.php';

/**
 * Class Init
 * @property Core2\Model\Modules $dataModules
 */
class Init extends Acl {

    /**
     * @var StdClass|Zend_Session_Namespace
     */
    private $auth;

    protected $is_rest = array();
    protected $is_soap = array();


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

        // Парсим маршрут
        $route = (new Router())::$route;
        if (isset($route['api']) && !$this->auth) {
            if ($route['api'] == 'auth') {
                //это запросы на регистрацию, восстановление пароля или OAUTH
                require_once 'core2/inc/classes/Api.php';
                header('Content-type: application/json; charset="utf-8"');
                try {
                    return (new Core2\Api($route))->dispatchApi();
                } catch (Exception $e) {
                    return Error::catchJsonException($e->getMessage(), $e->getCode());
                }
            } else {
                header('HTTP/1.1 401 Unauthorized');
                $core_config = Registry::get('core_config');
                if ($core_config->auth && $core_config->auth->scheme == 'basic') {
                    header("WWW-Authenticate: Basic realm={$core_config->auth->basic->realm}, charset=\"UTF-8\"");
                }
                return '';
            }
        }

        if ($res = $this->detectWebService()) return $res; //устаревший вызов REST и SOAP

        if (empty($_GET['system_page']) &&
            ! empty($this->auth->ID) &&
            ! empty($this->auth->NAME) &&
            is_int($this->auth->ID)
        ) {

            if (isset($route['module'])) {
                if (isset($route['api']) && $route['api'] === 'openapi') {
                    require_once "OpenApiSpec.php";
                    $schema = new \Core2\OpenApiSpec();

                    if ($route['action'] == 'sections') {
                        header('Content-Type: application/json');

                        if ( ! empty($route['params'])) {
                            $section = key($route['params']);
                            return json_encode($schema->getSectionSchema($section));
                        }

                        return json_encode([ 'sections' => $schema->getSections() ]);
                    }
                }
                elseif ($route['module'] === 'sse') {

                    $this->setContext("admin", "sse");
                    session_write_close();
                    header("Content-Type: text/event-stream; charset=utf-8");
                    header("X-Accel-Buffering: no");
                    header("Cache-Control: no-cache");

                    $sse = new Core2\SSE();
                    $sse->run();
                    return '';
                }
            }

            // LOG USER ACTIVITY
            $logExclude = array(
                'profile/index/unread', //Запросы на проверку не прочитанных сообщений не будут попадать в журнал запросов
            );

            $this->logActivity($logExclude);
            //TODO CHECK DIRECT REQUESTS except iframes

            require_once 'Zend_Session_Namespace.php'; //DEPRECATED
            require_once 'core2/inc/Interfaces/Delete.php';
            require_once 'core2/inc/Interfaces/File.php';
            require_once 'core2/inc/Interfaces/Subscribe.php';
            require_once 'core2/inc/Interfaces/Switches.php';

            $this->setupAcl();

            if ($you_need_to_pay = $this->checkBilling()) return $you_need_to_pay;

            if (!empty($_POST)) {
                //может ли xajax обработать запрос
                $xajax = new xajax();
                if ($xajax->canProcessRequest()) {
                    $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
                    $xajax->processRequest();
                    return '';
                }
                else {
                    unset($xajax);
                }
            }

        }
        else {
            require_once 'Login.php';

            $login = new Login();
            $this->setupSkin();
            parse_str($route['query'], $request);
            if (array_key_exists('X-Requested-With', Tool::getRequestHeaders())) {
                if ( ! empty($request['module'])) {
                    throw new Exception('expired');
                }
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if ( ! empty($_POST['xjxr'])) {
                    throw new Exception('expired');
                }
                if (empty($_SERVER['HTTP_REFERER'])) {
                    throw new Exception('Referrer error');
                }
                $referer = parse_url($_SERVER['HTTP_REFERER']);
                if (empty($referer['host']) || $referer['host'] !== $_SERVER['HTTP_HOST']) {
                    http_response_code(400);
                    throw new Exception('Referrer error');
                }
                if (isset($_POST['login']) && isset($_POST['password'])) {
                    return json_encode(
                        $login->enter(trim($_POST['login']), trim($_POST['password']), $_GET['return_url'] ?? null)
                    );
                }
            }

            //Immutable блокирует запись сессии
            //SessionContainer::getDefaultManager()->getStorage()->markImmutable();
            $response = $login->dispatch($route);
            $blockNamespace = new SessionContainer('Block');
            if (empty($blockNamespace->blocked)) {
                SessionContainer::getDefaultManager()->destroy();
            }
            return $response;
        }

        //$requestDir = str_replace("\\", "/", dirname($_SERVER['REQUEST_URI']));

        if (
            empty($_GET['module']) && empty($route['api']) && empty($_POST) &&
            ($_SERVER['REQUEST_URI'] == $_SERVER['SCRIPT_NAME'] ||
            trim($_SERVER['REQUEST_URI'], '/') == trim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'])), '/'))
        ) {
            require_once 'Menu.php';

            $menu = new Core2\Menu();
            if (empty($this->auth->init)) { //нет сессии на сервере
                header('Content-type: application/json; charset="utf-8"');
                return $menu->getMenuMobile();
            }
            $this->setupSkin();
            if (!defined('THEME')) return '';
            return $menu->getMenu();
        }
        else {
            if (!empty($route['api'])) {
                //---запрос от приложения
                require_once 'core2/inc/classes/Api.php';
                header('Content-type: application/json; charset="utf-8"');
                try {
                    return (new Core2\Api($route))->dispatchApi();
                } catch (Exception $e) {
                    return Error::catchJsonException($e->getMessage(), $e->getCode());
                }
            }
            $module = $route['module'];
            $extension = strrpos($module, '.') ? substr($module, strrpos($module, '.')) : null;
            if ($extension) $module = substr($module, 0, strrpos($module, '.'));
            if ($module == 'index') $module = "admin";

            if (!$module) throw new Exception($this->translate->tr("Модуль не найден"), 404);
            $action = $route['action'];
            $this->setContext($module, $action);

            if ($this->fileAction()) return '';

            $this->setupSkin();

            if ($module === 'admin') {

                if (!empty($this->auth->MOBILE)) {
                    require_once 'core2/inc/MobileController.php';
                    $core = new MobileController();
                }
                else {
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
                if (in_array($module, ['registration', 'registration_complete', 'restore', 'restore_complete'])) {
                    header("Location: index.php");
                    return '';
                }
                $this->checkModule($module, $action);
                $location = $this->getModuleLocation($module); //определяем местоположение модуля

                $mods = $this->getSubModule($module . '_' . $action);
                if (!empty($mods['sm_path'])) {
                    $path = parse_url($mods['sm_path']);
                    if (!empty($path['scheme'])) return "<script>loadExt('{$mods['sm_path']}')</script>";
                    if (file_exists($location . "/" . trim($path['path'], "/")))
                        return "<script>loadExt('{$this->getModuleSrc($module)}/{$mods['sm_path']}')</script>";
                }

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
                $modController = "Mod" . ucfirst(strtolower($module)) . "Controller";
                if (!empty($this->auth->MOBILE)) {
                    $modController = "Mobile" . ucfirst(strtolower($module)) . "Controller";
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

            }
        }
    }



    /**
     * проверка модуля на доступность
     * @param $module
     * @param $action
     * @return void
     * @throws \Core2\JsonException
     * @throws Exception
     */
    private function checkModule($module, $action): void {
        if ($action == 'index') {
            $_GET['action'] = "index";

            if ( ! $this->isModuleActive($module)) {
                throw new Exception(sprintf($this->translate->tr("Модуль %s не существует"), $module), 404);
            }

            if (! $this->checkAcl($module, 'access')) {
                throw new Exception(911);
            }
        }
        else {
            $submodule_id = $module . '_' . $action;
            if ( ! $this->isModuleActive($submodule_id)) {
                throw new Exception(sprintf($this->translate->tr("Субмодуль %s не существует"), $action), 404);
            }
            $mods = $this->getSubModule($submodule_id);

            //TODO перенести проверку субмодуля в контроллер модуля
            if ($mods['sm_id'] && !$this->checkAcl($submodule_id, 'access')) {
                throw new Exception(911);
            }
        }
    }



    /**
     *
     */
    public function __destruct() {

        if ($this->core_config->profile &&
            $this->core_config->profile->on
        ) {
            $log = new Core2\Log('profile');

            if ($log->getWriter()) {
                $connection_id = $this->db->fetchOne("SELECT CONNECTION_ID()");
                $profiler      = $this->db->getProfiler();
                $total_time    = $profiler->getTotalElapsedSecs();
                $queries       = [];
                $max_slow      = [];

                foreach ($profiler->getQueryProfiles() as $query) {
                    $time       = $query->getElapsedSecs();
                    $query_item = [
                        'time'  => $time,
                        'query' => $query->getQuery(),
                    ];

                    if (empty($max_slow['time']) || $max_slow['time'] < $time) {
                        $max_slow = $query_item;
                    }

                    $queries[] = $query_item;
                }

                $request_method = ! empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'none';
                $request_uri    = ! empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                $query_string   = ! empty($_SERVER['QUERY_STRING']) ? "?{$_SERVER['QUERY_STRING']}" : '';
                $function_log   = $total_time >= 1 || count($queries) >= 100 || count($queries) == 0
                    ? 'warning'
                    : 'info';


                $log->{$function_log}('request', [
                    'method'        => $request_method,
                    'time'          => round($total_time, 5),
                    'count'         => count($queries),
                    'connection_id' => $connection_id,
                    'request'       => "{$request_uri}{$query_string}",
                    'max_slow'      => $max_slow,
                    'queries'       => $queries,
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
     * @deprecated
     */
    private function detectWebService() {

        if ( ! isset($_SERVER['REQUEST_URI'])) {
            return false;
        }
        $is_rest = false;
        $is_soap = false;
        $matches = [];
        if (preg_match('~api/v(?<version>\d+\.\d+)(?:/|)([^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
            $is_rest = [
                'version' => $matches['version'],
                'action'  => $matches[2]
            ];
        }
        else if (preg_match('~api/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d+\.\d+)(?:/)(?<action>[^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
            $is_rest = $matches;
        }
        else if (preg_match('~rest/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d+\.\d+)(?:/)(?<action>[^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
            $is_rest = $matches;
        }
        else if (preg_match('~^(wsdl_([a-zA-Z0-9_]+)\.xml|ws_([a-zA-Z0-9_]+)\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {
            $is_soap = [
                'module'  => ! empty($matches[2]) ? $matches[2] : $matches[3],
                'version' => '',
                'action'  => ! empty($matches[2]) ? 'wsdl.xml' : 'service.php',
            ];
        }
        else if (preg_match('~soap/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d+\.\d+)/(?<action>wsdl\.xml|service\.php)~', $_SERVER['REQUEST_URI'], $matches)) {
            $is_soap = $matches;
        }
        if (!$is_rest && !$is_soap) return false;

        $this->setContext('webservice');
        $this->checkWebservice();
        $webservice_controller = new ModWebserviceController();

        // Веб-сервис (REST)
        if ($is_rest) {
            $route['version'] = $is_rest['version'];
            $route['query'] = $_SERVER['QUERY_STRING'];
            $route['params'] = [];
            if (!empty($is_rest['module'])) {
                $route['module'] = $is_rest['module'];
            }
            if (!empty($is_rest['action'])) {
                $route['action'] = $is_rest['action'];
            }
            $res = $webservice_controller->dispatchRest($route);
        }

        // Веб-сервис (SOAP)
        if ($is_soap) {
            $action = $is_soap['action'] == 'service.php' ? 'server' : 'wsdl';
            $res = $webservice_controller->dispatchSoap($is_soap['module'], $action, $is_soap['version']);
        }
        if (!$res) return true;
        return $res;
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
                $webservice_api = new ModWebserviceApi();
                //требуется webservice 2.6.0
                return $webservice_api->dispatchToken($token);
            }
            if (strpos($_SERVER['HTTP_AUTHORIZATION'], 'Basic') === 0) {
                $core_config = Registry::get('core_config');
                if ($core_config->auth && $core_config->auth->scheme == 'basic') {
                    //http basic auth allowed
                    [$login, $password] = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
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
            $webservice_api = new ModWebserviceApi();
            return $webservice_api->dispatchWebToken($token);
        }
        elseif (!empty($_GET['apikey']) || !empty($_SERVER['HTTP_CORE2_APIKEY'])) {
            $apikey  = ! empty($_SERVER['HTTP_CORE2_APIKEY']) ? trim($_SERVER['HTTP_CORE2_APIKEY']) : trim($_GET['apikey']);
            //DEPRECATED ктото пытается авторизовать запрос при помощи api ключа
            // ключ проверим в webservice, если такой есть, то пропустим запрос, как если бы он авторизовался легальным способом
            $this->checkWebservice();
            $webservice_api = new ModWebserviceApi();
            return $webservice_api->dispatchApikey(trim($apikey));
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
                    $res = $modController->action_filehandler($context, $table, (int) $id);
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
            $this->allow($this->auth->ROLE, 'billing');
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