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
require_once("Log.php");
require_once("Theme.php");
require_once 'Registry.php';
require_once 'Config.php';

use Laminas\Session\Config\SessionConfig;
use Laminas\Session\SessionManager;
use Laminas\Session\SaveHandler\Cache AS SessionHandlerCache;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\Validator\HttpUserAgent;
use Laminas\Cache\Storage;
use Core2\Registry;
use Core2\Tool;

if ( ! empty($_SERVER['REQUEST_URI'])) {
    $f = explode(".", basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if (!empty($f[1]) && in_array($f[1], ['txt', 'js', 'css', 'html'])) {
        \Core2\Error::Exception("File not found", 404);
    }
}

$conf_file = DOC_ROOT . "conf.ini";

if (!file_exists($conf_file)) {
    \Core2\Error::Exception("conf.ini is missing.", 404);
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
        ],
        'driver_options'=> [
            \PDO::ATTR_TIMEOUT => 3,
        ],
        'isDefaultTableAdapter' => true,
        'profiler'              => [
            'enabled' => false,
            'class'   => 'Zend_Db_Profiler_Firebug',
        ],
        'caseFolding'                => true,
        'autoQuoteIdentifiers'       => true,
        'allowSerialization'         => true,
        'autoReconnectOnUnserialize' => true,
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

    $config = new Core2\Config($config);
    $config2   = $config->readIni($conf_file, $section);
    $conf_d = DOC_ROOT . "conf.ext.ini";
    if (file_exists($conf_d)) {
        $config_ext = new Core2\Config();
        $config2->merge($config_ext->readIni($conf_d, $section));
    }
    echo "<PRE>";print_r($config->database);echo "</PRE>";//die;
    echo "<PRE>";print_r($config2->database);echo "</PRE>";die;

}
catch (Exception $e) {
    \Core2\Error::Exception($e->getMessage());
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
}

//конфиг стал только для чтения
$config->setReadOnly();

if (isset($config->include_path) && $config->include_path) {
    set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
}

//подключаем мультиязычность
require_once 'I18n.php';
$translate = new \Core2\I18n($config);

//устанавливаем шкурку
if ( ! empty($config->theme)) {
    define('THEME', $config->theme);

} elseif ( ! empty($config->system->theme) &&
           ! empty($config->system->theme->name)
) {
    define('THEME', $config->system->theme->name);

} else {
    //define('THEME', 'default');
}
if (defined('THEME')) {
    $theme_model = __DIR__ . "/../../html/" . THEME . "/model.json";
    if (!file_exists($theme_model)) {
        \Core2\Error::Exception("Theme '" . THEME . "' model does not exists.");
    }
    $tpls = file_get_contents($theme_model);
    \Core2\Theme::set(THEME, $tpls);
}

//сохраняем параметры сессии
if ($config->session) {
    $sess_config = new SessionConfig();
    $sess_config->setOptions($config->session);
    $sess_manager = new SessionManager($sess_config);
    $sess_manager->getValidatorChain()->attach('session.validate', [new HttpUserAgent(), 'isValid']);
    if ($config->session->phpSaveHandler) {
        $options = ['namespace' => $_SERVER['SERVER_NAME'] . ":Session"];
        if ($config->session->remember_me_seconds) $options['ttl'] = $config->session->remember_me_seconds;
        if ($config->session->savePath) $options['server'] = $config->session->savePath;

        if ($config->session->saveHandler === 'memcached') {
            $adapter  = new Storage\Adapter\Memcached($options);
            $sess_manager->setSaveHandler(new SessionHandlerCache($adapter));
        }
        elseif ($config->session->phpSaveHandler === 'redis') {
            $adapter  = new Storage\Adapter\Redis($options);
            $sess_manager->setSaveHandler(new SessionHandlerCache($adapter));
        }
    }

    //сохраняем менеджер сессий
    SessionContainer::setDefaultManager($sess_manager);
}

//сохраняем конфиг
Registry::set('config', $config);

//обрабатываем конфиг ядра
$core_conf_file = __DIR__ . "/../../conf.ini";
if (file_exists($core_conf_file)) {
    $config = new Core2\Config();
    $core_config   = $config->readIni($core_conf_file, 'production');
    Registry::set('core_config', $core_config);
}

require_once 'Db.php';
require_once 'Common.php';
require_once 'Templater2.php'; //DEPRECATED
require_once 'Templater3.php';
require_once 'Login.php';
require_once 'SSE.php';


/**
 * Class Init
 * @property Modules $dataModules
 */
class Init extends \Core2\Db {

        /**
         * @var StdClass|Zend_Session_Namespace
         */
        protected $auth;

        /**
         * @var \Core2\Acl
         */
        protected $acl;
        protected $is_cli = false;
        protected $is_rest = array();
        protected $is_soap = array();


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

            $this->detectWebService();

            if ($this->is_rest || $this->is_soap) {
                Registry::set('auth', new StdClass());
                return;
            }

            // проверяем, есть ли в запросе токен авторизации
            $auth = $this->checkToken();
            if ($auth) { //произошла авторизация по токену
                $this->auth = $auth;
                Registry::set('auth', $this->auth);
                return; //выходим, если авторизация состоялась
            }

            if (PHP_SAPI === 'cli') {
                $this->is_cli = true;
                Registry::set('auth', new StdClass());
                return;
            }
            //$s = SessionContainer::getDefaultManager()->sessionExists();
            $this->auth = new SessionContainer('Auth');

            if ( ! empty($this->auth->ID) && $this->auth->ID > 0) {
                if (!$this->auth->getManager()->isValid()) {
                    $this->closeSession('Y');
                }
                //is user active right now
                if ($this->isUserActive($this->auth->ID) && isset($this->auth->accept_answer) && $this->auth->accept_answer === true) {
                    if ($this->auth->LIVEID) {
                        $row = $this->dataSession->find($this->auth->LIVEID)->current();
                        if (isset($row->is_kicked_sw) && $row->is_kicked_sw == 'Y') {
                            $this->closeSession();
                        }
                    }
                    $sLife = $this->getSetting('session_lifetime');
                    if ($sLife) {
                        $this->auth->setExpirationSeconds($sLife, "accept_answer");
                    }
                } else {
                    $this->closeSession('Y');
                }
            }
            Registry::set('auth', $this->auth);
        }


        /**
         * The main dispatcher
         *
         * @return mixed|string
         * @throws Exception
         */
        public function dispatch() {

            if ($this->is_cli || PHP_SAPI === 'cli') {
                return $this->cli();
            }

            $this->detectWebService();

            // Веб-сервис (REST)
            if ($matches = $this->is_rest) {
                $this->setContext('webservice');

                $this->checkWebservice();

                require_once __DIR__ . "/../../inc/Interfaces/Delete.php"; //FIXME delete me

                $route            = $this->routeParse();
                $route['version'] = $matches['version'];

                if ( ! empty($matches['module'])) {
                    $route['module'] = $matches['module'];
                    $route['action'] = $matches['action'];
                }

                $webservice_controller = new ModWebserviceController();
                return $webservice_controller->dispatchRest($route);
            }

            // Веб-сервис (SOAP)
            if ($matches = $this->is_soap) {
                $this->setContext('webservice');
                $this->checkWebservice();

                $webservice_controller = new ModWebserviceController();

                $version     = $matches['version'];
                $action      = $matches['action'] == 'service.php' ? 'server' : 'wsdl';
                $module_name = $matches['module'];

                return $webservice_controller->dispatchSoap($module_name, $action, $version);
            }



            // Парсим маршрут
            $route = $this->routeParse();
            if (!empty($this->auth->ID) && !empty($this->auth->NAME) && is_int($this->auth->ID)) {

                if (isset($route['module']) && $route['module'] === 'sse') {

                    require_once 'core2/inc/Interfaces/Event.php';

                    $this->setContext("admin", "sse");
                    session_write_close();
                    header("Content-Type: text/event-stream; charset=utf-8");
                    header("X-Accel-Buffering: no");
                    header("Cache-Control: no-cache");

                    $sse = new Core2\SSE();
                    while (1) {

                        $sse->loop();

                        if ( connection_aborted() ) break;

                        sleep(1);
                    }
                    return;
                }

                // LOG USER ACTIVITY
                $logExclude = array(
                    'module=profile&unread=1', //Запросы на проверку не прочитанных сообщений не будут попадать в журнал запросов
                );

                $this->logActivity($logExclude);
                //TODO CHECK DIRECT REQUESTS except iframes

                require_once 'Zend_Session_Namespace.php'; //DEPRECATED
                require_once 'core2/inc/classes/Acl.php';
                require_once 'core2/inc/Interfaces/Delete.php';
                require_once 'core2/inc/Interfaces/File.php';
                require_once 'core2/inc/Interfaces/Subscribe.php';
                require_once 'core2/inc/Interfaces/Switches.php';

                // TODO move ACL to auth
                // найти способ для запросов с токеном без пользователя
                $this->acl = new \Core2\Acl();
                $this->acl->setupAcl();

                if ($you_need_to_pay = $this->checkBilling()) return $you_need_to_pay;

            }
            else {

                $login = new \Core2\Login();
                $login->setSystemName($this->getSystemName());
                $login->setFavicon($this->getSystemFavicon());
                return $login->dispatch($route);
            }

            //$requestDir = str_replace("\\", "/", dirname($_SERVER['REQUEST_URI']));

            if (
                empty($_GET['module']) && empty($route['api']) &&
                ($_SERVER['REQUEST_URI'] == $_SERVER['SCRIPT_NAME'] ||
                trim($_SERVER['REQUEST_URI'], '/') == trim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'])), '/'))
            ) {
                if (!defined('THEME')) return;

                if ($this->auth->MOBILE) {
                    return $this->getMenuMobile();
                }
                return $this->getMenu();
            }
            else {
                if (!empty($_POST)) {
                    //может ли xajax обработать запрос
                    $xajax = new xajax();
                    if ($xajax->canProcessRequest()) {
//                    $xajax->configure('javascript URI', 'core2/vendor/belhard/xajax');
//                    $xajax->configure('errorHandler', true);
                        $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
                        $xajax->processRequest();
                        return;
                    }
                }

                if ($this->deleteAction()) return '';
                if ($this->switchAction()) return '';

                $module = !empty($route['api']) ? $route['api'] : $route['module'];
                if (!$module) throw new Exception($this->translate->tr("Модуль не найден"), 404);
                $action = $route['action'];
                $this->setContext($module, $action);

                if ($this->fileAction()) return '';

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

                } else {
                    if ($action == 'index') {
                        $_GET['action'] = "index";

                        if ( ! $this->isModuleActive($module)) {
                            throw new Exception(sprintf($this->translate->tr("Модуль %s не существует"), $module), 404);
                        }

                        if ( ! $this->acl->checkAcl($module, 'access')) {
                            throw new Exception(911);
                        }
                    } else {
                        $submodule_id = $module . '_' . $action;
                        $mods = $this->getSubModule($submodule_id);

                        //TODO перенести проверку субмодуля в контроллер модуля
                        if (!$mods) throw new Exception(sprintf($this->translate->tr("Субмодуль %s не существует"), $action), 404);
                        if ($mods['sm_id'] && !$this->acl->checkAcl($submodule_id, 'access')) {
                            throw new Exception(911);
                        }
                    }

                    if (empty($mods['sm_path'])) {
                        $location = $this->getModuleLocation($module); //определяем местоположение модуля
                        if ($this->translate->isSetup()) {
                            $this->translate->setupExtra($location, $module);
                        }
                        if (!empty($route['api'])) {
                            //запрос от приложения
                            $modController = "Mod" . ucfirst(strtolower($module)) . "Api";
                        }
                        elseif ($this->auth->MOBILE) {
                            $modController = "Mobile" . ucfirst(strtolower($module)) . "Controller";
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

                    $request_method = ! empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'none';
                    $query_string   = ! empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

                    if ($total_time >= 1 || count($sql_queries) >= 100 || count($sql_queries) == 0) {
                        $function_log = 'warning';
                    } else {
                        $function_log = 'info';
                    }


                    $log->{$function_log}('request', [$request_method, round($total_time, 5), count($sql_queries), $query_string]);
                    $log->{$function_log}('  | max slow', $max_slow);
                    $log->{$function_log}('  | queries ', $sql_queries);
                }
            }
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
                if (substr($_SERVER['HTTP_AUTHORIZATION'], 0, 5) == 'Basic') {
                    list($login, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
                    $user = $this->dataUsers->getUserByLogin($login);
                    if (!$user || $user['u_pass'] !== Tool::pass_salt(md5($password))) {
                        header("Location: " . DOC_PATH . "auth");
                        return;
                    }
                }
                if (strpos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer') === 0) {
                    $token = $_SERVER['HTTP_AUTHORIZATION'];
                }
                //TODO сделать поддержку других видов авторизации
                if (!$token) return;
                //TODO заменить модуль webservice на модуль auth
                $this->setContext('webservice');
                $this->checkWebservice();
                $webservice_controller = new ModWebserviceController();
                //требуется webservice 2.6.0
                return $webservice_controller->dispatchJwtToken($token);
            }
            elseif ( ! empty($_SERVER['HTTP_CORE2M'])) {
                //DEPRECATED в будущих версиях авторизоваться с таким токеном будет нельзя
                if (strpos($_SERVER['HTTP_CORE2M'], 'Bearer') === 0) {
                    $token = $_SERVER['HTTP_CORE2M'];
                }
                if (!$token) return;
                $this->setContext('webservice');
                $this->checkWebservice();
                $webservice_controller = new ModWebserviceController();
                return $webservice_controller->dispatchWebToken($token);
            }
        }


        /**
         * Проверка на наличие и работоспособности модуля Webservice
         */
        private function checkWebservice() {

            if ( ! $this->isModuleActive('webservice')) {
                \Core2\Error::catchJsonException([
                    'error_code'    => 'webservice_not_active',
                    'error_message' => $this->translate->tr('Модуль Webservice не активен')
                ], 503);
            }

            $location = $this->getModuleLocation('webservice');
            $webservice_controller_path =  $location . '/ModWebserviceController.php';

            if ( ! file_exists($webservice_controller_path)) {
                \Core2\Error::catchJsonException([
                    'error_code'    => 'webservice_not_isset',
                    'error_message' => $this->translate->tr('Модуль Webservice не существует')
                ], 500);
            }

            $autoload = $location . "/vendor/autoload.php";
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            require_once($webservice_controller_path);

            if ( ! class_exists('ModWebserviceController')) {
                \Core2\Error::catchJsonException([
                    'error_code'    => 'webservice_broken',
                    'error_message' => $this->translate->tr('Модуль Webservice сломан')
                ], 500);
            }
            Registry::set('auth', new StdClass()); //Необходимо для правильной работы контроллера
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
            $xajax->processRequest(); //DEPRECATED

            if (Tool::isMobileBrowser()) {
                $tpl_file      = \Core2\Theme::get("indexMobile");
                $tpl_file_menu = \Core2\Theme::get("menuMobile");
            } else {
                $tpl_file      = \Core2\Theme::get("index");
                $tpl_file_menu = \Core2\Theme::get("menu");
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
                if ( ! empty($module['sm_key'])) {
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

                            if (($modController instanceof TopJs || method_exists($modController, 'topJs')) &&
                                $module_js_list = $modController->topJs()
                            ) {
                                foreach ($module_js_list as $k => $module_js) {
                                    $modules_js[] = Tool::addSrcHash($module_js);
                                }
                            }

                            if ($modController instanceof TopCss &&
                                $module_css_list = $modController->topCss()
                            ) {
                                foreach ($module_css_list as $k => $module_css) {
                                    $modules_css[] = Tool::addSrcHash($module_css);
                                }
                            }

                            if (THEME !== 'default') {
                                $navigate_items[$module_id] = $this->getModuleNavigation($module['module_id'], $modController);
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
                foreach ($navigate_items as $module_name => $items) {
                    if ( ! empty($items)) {
                        foreach ($items as $item) {
                            $position = ! empty($item['position']) ? $item['position'] : '';

                            switch ($position) {
                                case 'profile':
                                    if ($tpl_menu->issetBlock('navigate_item_profile')) {
                                        $tpl_menu->navigate_item_profile->assign('[MODULE_NAME]', $module_name);
                                        $tpl_menu->navigate_item_profile->assign('[HTML]',        $this->renderNavigateItem($item));
                                        $tpl_menu->navigate_item_profile->reassign();
                                    }
                                    break;

                                case 'main':
                                default:
                                    if ($tpl_menu->issetBlock('navigate_item')) {
                                        $tpl_menu->navigate_item->assign('[MODULE_NAME]', $module_name);
                                        $tpl_menu->navigate_item->assign('[HTML]',        $this->renderNavigateItem($item));
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
         * @param $navigate_item
         * @return string
         * @throws Exception
         */
        private function renderNavigateItem($navigate_item) {

            if (empty($navigate_item['type'])) {
                return '';
            }

            $html = '';
            switch ($navigate_item['type']) {
                case 'divider':
                    $html = file_get_contents(\Core2\Theme::get("html-navigation-divider"));
                    break;

                case 'link':
                    $link = ! empty($navigate_item['link'])
                        ? $navigate_item['link']
                        : '#';
                    $on_click = ! empty($navigate_item['onclick'])
                        ? $navigate_item['onclick']
                        : "if (event.button === 0 && ! event.ctrlKey) load('{$link}');";

                    $tpl = new Templater3(\Core2\Theme::get("html-navigation-link"));
                    $tpl->assign('[TITLE]',   ! empty($navigate_item['title']) ? $navigate_item['title'] : '');
                    $tpl->assign('[ICON]',    ! empty($navigate_item['icon']) ? $navigate_item['icon'] : '');
                    $tpl->assign('[CLASS]',   ! empty($navigate_item['class']) ? $navigate_item['class'] : '');
                    $tpl->assign('[ID]',      ! empty($navigate_item['id']) ? $navigate_item['id'] : '');
                    $tpl->assign('[LINK]',    $link);
                    $tpl->assign('[ONCLICK]', $on_click);
                    $html = $tpl->render();
                    break;

                case 'dropdown':
                    $tpl = new Templater3(\Core2\Theme::get("html-navigation-dropdown"));
                    $tpl->assign('[TITLE]', ! empty($navigate_item['title']) ? $navigate_item['title'] : '');
                    $tpl->assign('[ICON]',  ! empty($navigate_item['icon'])  ? $navigate_item['icon']  : '');
                    $tpl->assign('[CLASS]', ! empty($navigate_item['class']) ? $navigate_item['class'] : '');

                    if ( ! empty($navigate_item['items'])) {
                        foreach ($navigate_item['items'] as $list_item) {

                            switch ($list_item['type']) {
                                case 'link':
                                    $link = ! empty($list_item['link'])
                                        ? $list_item['link']
                                        : '#';
                                    $on_click = ! empty($list_item['onclick'])
                                        ? $list_item['onclick']
                                        : "if (event.button === 0 && ! event.ctrlKey) load('{$link}');";

                                    $tpl->item->link->assign('[TITLE]',   ! empty($list_item['title']) ? $list_item['title'] : '');
                                    $tpl->item->link->assign('[ICON]',    ! empty($list_item['icon']) ? $list_item['icon'] : '');
                                    $tpl->item->link->assign('[CLASS]',   ! empty($list_item['class']) ? $list_item['class'] : '');
                                    $tpl->item->link->assign('[ID]',      ! empty($list_item['id']) ? $list_item['id'] : '');
                                    $tpl->item->link->assign('[LINK]',    $link);
                                    $tpl->item->link->assign('[ONCLICK]', $on_click);
                                    break;

                                case 'file':
                                    $on_change = ! empty($list_item['onchange'])
                                        ? $list_item['onchange']
                                        : "";

                                    $tpl->item->file->assign('[TITLE]',    ! empty($list_item['title']) ? $list_item['title'] : '');
                                    $tpl->item->file->assign('[ICON]',     ! empty($list_item['icon']) ? $list_item['icon'] : '');
                                    $tpl->item->file->assign('[CLASS]',    ! empty($list_item['class']) ? $list_item['class'] : '');
                                    $tpl->item->file->assign('[ID]',       ! empty($list_item['id']) ? $list_item['id'] : '');
                                    $tpl->item->file->assign('[ONCHANGE]', $on_change);
                                    break;

                                case 'divider':
                                    $tpl->item->touchBlock('divider');
                                    break;

                                case 'header':
                                    $tpl->item->header->assign('[TITLE]', ! empty($list_item['title']) ? $list_item['title'] : '');
                                    break;
                            }

                            $tpl->item->reassign();
                        }
                    }

                    $html = $tpl->render();
                    break;
            }

            return $html;
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
				if ($this->acl->checkAcl($data['module_id'], 'access')) {
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
         * @param $name
         * @param $mod_controller
         * @return array
         * @throws Zend_Config_Exception
         */
        private function getModuleNavigation($name, $mod_controller): array {

            require_once 'Navigation.php';

            $config_module = $this->getModuleConfig($name);
            $navigation    = new Core2\Navigation();

            if ( ! empty($config_module) &&
                 ! empty($config_module->system) &&
                 ! empty($config_module->system->nav)
            ) {
                $navigations = $config_module->system->nav->toArray();

                if ( ! empty($navigations)) {
                    foreach ($navigations as $key => $nav) {
                        if ( ! empty($nav['type'])) {
                            $nav['position'] = $nav['position'] ?? '';

                            switch ($nav['type']) {
                                case 'link':
                                    $nav['title'] = $nav['title'] ?? '';
                                    $nav['link']  = $nav['link'] ?? '#';

                                    $nav_link = $navigation->addLink($nav['title'], $nav['link'], $nav['position']);

                                    if ( ! empty($nav['icon'])) {
                                        $nav_link->setIcon($nav['icon']);
                                    }
                                    if ( ! empty($nav['id'])) {
                                        $nav_link->setId($nav['id']);
                                    }
                                    if ( ! empty($nav['class'])) {
                                        $nav_link->setClass($nav['class']);
                                    }
                                    if ( ! empty($nav['onclick'])) {
                                        $nav_link->setOnClick($nav['onclick']);
                                    }
                                    break;

                                case 'divider':
                                    $navigation->addDivider($nav['position']);
                                    break;

                                case 'dropdown':
                                    $nav['title'] = $nav['title'] ?? '';
                                    $nav['items'] = $nav['items'] ?? [];

                                    $nav_list = $navigation->addDropdown($nav['title'], $nav['position']);

                                    if ( ! empty($nav['icon'])) {
                                        $nav_list->setIcon($nav['icon']);
                                    }
                                    if ( ! empty($nav['class'])) {
                                        $nav_list->setClass($nav['class']);
                                    }

                                    if ( ! empty($nav['items'])) {
                                        foreach ($nav['items'] as $item) {

                                            switch ($item['type']) {
                                                case 'link':
                                                    $item['title'] = $item['title'] ?? '';
                                                    $item['link']  = $item['link'] ?? '#';

                                                    $item_link = $nav_list->addLink($item['title'], $item['link']);

                                                    if ( ! empty($item['id'])) {
                                                        $item_link->setId($item['id']);
                                                    }
                                                    if ( ! empty($item['class'])) {
                                                        $item_link->setClass($item['class']);
                                                    }
                                                    if ( ! empty($item['icon'])) {
                                                        $item_link->setIcon($item['icon']);
                                                    }
                                                    if ( ! empty($item['onclick'])) {
                                                        $item_link->setOnClick($item['onclick']);
                                                    }
                                                    break;

                                                case 'header':
                                                    $item['title'] = $item['title'] ?? '';
                                                    $nav_list->addHeader($item['title']);
                                                    break;

                                                case 'divider':
                                                    $nav_list->addDivider();
                                                    break;

                                                case 'file':
                                                    $item['title'] = $item['title'] ?? '';
                                                    $item_file = $nav_list->addFile($item['title']);

                                                    if ( ! empty($item['id'])) {
                                                        $item_file->setId($item['id']);
                                                    }
                                                    if ( ! empty($item['class'])) {
                                                        $item_file->setClass($item['class']);
                                                    }
                                                    if ( ! empty($item['icon'])) {
                                                        $item_file->setIcon($item['icon']);
                                                    }
                                                    if ( ! empty($item['onchange'])) {
                                                        $item_file->setOnChange($item['onchange']);
                                                    }
                                                    break;
                                            }
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                }
            }

            if ($mod_controller instanceof Navigation) {
                $mod_controller->navigationItems($navigation);
            }

            return $navigation->toArray();
        }


        /**
         * Cli
         * @return string
         * @throws Exception
         */
        private function cli() {

	        $options = getopt('m:a:p:s:h', array(
	            'module:',
	            'action:',
	            'param:',
	            'section:',
	            'help',
	        ));


	        if (empty($options) || isset($options['h']) || isset($options['help'])) {
	            return implode(PHP_EOL, array(
	                'Core 2',
	                'Usage: php index.php [OPTIONS]',
	                'Optional arguments:',
	                "   -m    --module    Module name",
	                "   -a    --action    Cli method name",
	                "   -p    --param     Parameter in method",
	                "   -s    --section   Section name in config file",
					"   -h    --help      Help info",
					"Examples of usage:",
	                "php index.php --module cron --action run",
	                "php index.php --module cron --action run --section site.com",
	                "php index.php --module cron --action runJob --param 123\n",
	            ));
	        }

	        if ((isset($options['m']) || isset($options['module'])) &&
	            (isset($options['a']) || isset($options['action']))
	        ) {
	            $module = isset($options['module']) ? $options['module'] : $options['m'];
	            $action = isset($options['action']) ? $options['action'] : $options['a'];
                $this->setContext($module, $action);
	            $params = isset($options['param'])
	                ? $options['param']
	                : (isset($options['p']) ? $options['p'] : false);
	            $params = $params === false
	                ? array()
	                : (is_array($params) ? $params : array($params));

	            try {
	                $this->db; // FIXME хак

	                if ( ! $this->isModuleInstalled($module)) {
	                    throw new Exception("Module '$module' not found");
	                }

	                if ( ! $this->isModuleActive($module)) {
	                    throw new Exception("Module '$module' does not active");
	                }

	                $location     = $this->getModuleLocation($module);
	                $mod_cli      = 'Mod' . ucfirst(strtolower($module)) . 'Cli';
	                $mod_cli_path = "{$location}/{$mod_cli}.php";

	                if ( ! file_exists($mod_cli_path)) {
	                    throw new Exception(sprintf($this->_("File '%s' does not exists"), $mod_cli_path));
	                }

	                require_once $mod_cli_path;

	                if ( ! class_exists($mod_cli)) {
	                    throw new Exception(sprintf($this->_("Class '%s' not found"), $mod_cli));
	                }


                    $all_class_methods = get_class_methods($mod_cli);
                    if ($parent_class = get_parent_class($mod_cli)) {
                        $parent_class_methods = get_class_methods($parent_class);
                        $self_methods = array_diff($all_class_methods, $parent_class_methods);
                    } else {
                        $self_methods = $all_class_methods;
                    }

	                if (array_search($action, $self_methods) === false) {
	                    throw new Exception(sprintf($this->_("Cli method '%s' not found in class '%s'"), $action, $mod_cli));
	                }

                    $autoload_file = $location . "/vendor/autoload.php";
                    if (file_exists($autoload_file)) {
                        require_once($autoload_file);
                    }

	                $mod_instance = new $mod_cli();
	                $result = call_user_func_array(array($mod_instance, $action), $params);

	                if (is_scalar($result)) {
	                    return (string)$result . PHP_EOL;
	                }

	            } catch (\Exception $e) {
	                $message = $e->getMessage();
	                return $message . PHP_EOL;
	            }

	        }

			return PHP_EOL;
		}


        /**
         * Основной роутер
         */
        private function routeParse() {
            $temp  = explode("/", DOC_PATH);
            $temp2 = explode("/", $_SERVER['REQUEST_URI']);
            $i = -1;
            foreach ($temp as $k => $v) {
                if ($temp2[$k] == $v) {
                    $i++;
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
                if ($co > 1 || current($temp2) === 'auth') {
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
                    $vv  = explode("?", current($temp2));
                    if (!empty($vv[1])) {
                        parse_str($vv[1], $_GET);
                    }
                    $route['module'] = $vv[0];
                    if (!$route['module'] || strpos($route['module'], '.')) { //DEPRECATED
                        // FIXME Убрать модуль и экшен по умолчанию
                        $route['module'] = !empty($_GET['module']) ? $_GET['module'] : 'admin';
                        $route['action'] = !empty($_GET['action']) ? $_GET['action'] : 'index';
                    }
                }
            }
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
                $modController = "Mobile" . ucfirst(strtolower($data['module_id'])) . "Controller";
                if ( ! file_exists($location . "/$modController.php")) {
                    unset($modsList[$k]); //FIXME если это не выполнится, core2m не будет работать!
                } else {
                    require_once $location . "/$modController.php";
                    $r = new \ReflectionClass($modController);
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
                'avatar'      => "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))),
                'required_location' => false,
                'modules'     => $modsList
            ];
            if ($this->config->mobile) { //Настройки для Core2m
                if ($this->config->mobile->required && $this->config->mobile->required->location) {
                    $data['required_location'] = true; //требовать геолокацию для работы
                }
            }

            return json_encode([
                'status' => 'success',
                'data'   => $data,
            ] + $data); // Для совместимости с разными приложениями
        }


        /**
         * Установка контекста выполнения скрипта
         * @param string $module
         * @param string $action
         */
        private function setContext($module, $action = 'index') {
            $registry     = \Core2\Registry::getInstance();
            //$registry 	= new ServiceManager();
            //$registry->setAllowOverride(true);
            $registry->set('context', array($module, $action));
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

    $acl = new \Core2\Acl();

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
                \Core2\Error::catchXajax($e, $res);
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

        $db        = new \Core2\Db;
        $location  = $db->getModuleLocation($route['module']);
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
                    \Core2\Error::catchXajax($e, $res);
                }
            } else {
                throw new BadMethodCallException($translate->tr("Метод не найден"), 60);
            }
        }
    }

    return $res;
}