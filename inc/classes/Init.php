<?
    header('Content-Type: text/html; charset=utf-8');

    // Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
    define("DOC_ROOT", dirname($_SERVER['SCRIPT_FILENAME']) . "/");
    define("DOC_PATH", substr(DOC_ROOT, strlen($_SERVER['DOCUMENT_ROOT'])) ? : '/');

    $conf_file = DOC_ROOT . "core2/vendor/autoload.php";
    if (!file_exists($conf_file)) {
        \Core2\Error::Exception("Отсутствует загрузчик.");
    }
    require_once($conf_file);
    require_once("Error.php");

    $conf_file = DOC_ROOT . "conf.ini";

    if (!file_exists($conf_file)) {
        \Core2\Error::Exception("Отсутствует конфигурационный файл.");
    }
    $config = array(
        'system'       => array('name' => 'CORE'),
        'include_path' => '',
        'cache'        => 'cache',
        'temp'         => getenv('TMP'),
        'debug'        => array('on' => false),
        'session'      => array('cookie_httponly'  => true,
                                'use_only_cookies' => true),
        'database'     => array(
            'adapter'                    => 'Pdo_Mysql',
            'params'                     => array(
                'charset'          => 'utf8',
                'adapterNamespace' => 'Core_Db_Adapter'
            ),
            'isDefaultTableAdapter'      => true,
            'profiler'                   => array(
                'enabled' => false,
                'class'   => 'Zend_Db_Profiler_Firebug'
            ),
            'caseFolding'                => true,
            'autoQuoteIdentifiers'       => true,
            'allowSerialization'         => true,
            'autoReconnectOnUnserialize' => true
        )
    );
	// определяем путь к темповой папке
    if (empty($config['temp'])) {
        $config['temp'] = sys_get_temp_dir();
        if (empty($config['temp'])) {
            $config['temp'] = "/tmp";
        }
    }

    //обрабатываем общий конфиг
    try {
        $config = new Zend_Config($config, true);

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

        if (!empty($_SERVER['SERVER_NAME'])) {
            $config2 = new Zend_Config_Ini($conf_file, $_SERVER['SERVER_NAME']);
        } else {
            $config2 = new Zend_Config_Ini($conf_file, 'production');
        }
        $config->merge($config2);
    } catch (Zend_Config_Exception $e) {
        \Core2\Error::Exception($e->getMessage());
    }

    // отладка приложения
    if ($config->debug->on) {
        ini_set('display_errors', 1);
    } else {
        ini_set('display_errors', 0);
    }

    // определяем путь к папке кеша
    if (strpos($config->cache, '/') !== 0) {
        $config->cache = DOC_ROOT . trim($config->cache, "/");
    }

    //подключаем собственный адаптер базы данных
    require_once($config->database->params->adapterNamespace . "_{$config->database->adapter}.php");

    //конфиг стал только для чтения
    $config->setReadOnly();

    if (isset($config->include_path) && $config->include_path) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
    }

    //подключаем мультиязычность
    require_once 'I18n.php';
    $translate = new I18n($config);

	if (isset($config->auth) && $config->auth->on) {
		$realm = $config->auth->params->realm;
		$users = $config->auth->params->users;
		if ($code = Tool::httpAuth($realm, $users)) {
			if ($code == 1) \Core2\Error::Exception("Неверный пользователь.");
			if ($code == 2) \Core2\Error::Exception("Неверный пароль.");
		}
	}

	require_once("Log.php");

	//устанавливаем шкурку
	if (!empty($config->theme)) {
		define('THEME', $config->theme);
	} else {
		define('THEME', 'default');
	}

	// DEPRECATED!!! MPDF PATH
	define("_MPDF_TEMP_PATH", rtrim($config->cache, "/") . '/');
	define("_MPDF_TTFONTDATAPATH", rtrim($config->cache, "/") . '/');

	//сохраняем параметры сессии
	if ($config->session) {
		Zend_Session::setOptions($config->session->toArray());
	}

	//сохраняем конфиг
	Zend_Registry::set('config', $config);

    //обрабатываем конфиг ядра
    $core_conf_file = __DIR__ . "/../../conf.ini";
    if (file_exists($core_conf_file)) {
        $core_config = new Zend_Config_Ini($core_conf_file, 'production');
        Zend_Registry::set('core_config', $core_config);
    }

	require_once 'Db.php';
	require_once 'Common.php';
	require_once 'Templater.php'; //DEPRECATED
	require_once 'Templater2.php';


	/**
	 * Class Init
     */
    class Init extends Db {

        protected $auth;
        protected $tpl;
        protected $acl;
        private $is_cli = false;
        private $is_rest = false;
        private $is_soap = false;

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
         * @throws Zend_Session_Exception
         */
        public function checkAuth() {
            // проверяем, есть ли в запросе токен
            $auth = $this->checkToken();
            if ($auth) { //произошла авторизация по токену
                $this->auth = $auth;
                Zend_Registry::set('auth', $this->auth);
                return;
            }
            if (preg_match('~api/([a-zA-Z0-9_]+)(?:/|)([^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
                $this->is_rest = true;
                return;
            }
            if (preg_match('~^(wsdl_([a-zA-Z0-9_]+)\.xml|ws_([a-zA-Z0-9_]+)\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {
                $this->is_soap = true;
                return;
            }
            if (PHP_SAPI === 'cli') {
                $this->is_cli = true;
                return;
            }

            $this->auth 	= new Zend_Session_Namespace('Auth', true);
            if (!isset($this->auth->initialized)) { //регенерация сессии для предотвращения угона
                Zend_Session::regenerateId();
                $this->auth->initialized = true;
            }
            Zend_Registry::set('auth', $this->auth); // сохранение сессии в реестре

            if (!empty($this->auth->ID) && $this->auth->ID > 0) {
                //is user active right now
                if ($this->isUserActive($this->auth->ID) && isset($this->auth->accept_answer) && $this->auth->accept_answer === true) {
                    $sLife = $this->getSetting('session_lifetime');
                    if ($sLife) {
                        $this->auth->setExpirationSeconds($sLife, "accept_answer");
                    }
                    $this->auth->lock();
                } else {
                    $this->closeSession('Y');
                    Zend_Session::destroy();
                    //$this->auth->ID = 0;
                    //$this->auth->NAME = '';
                }
                Zend_Registry::set('auth', $this->auth);
            }
        }

        /**
         * Проверка наличия токена в запросе
         *
         * @return StdClass|void
         */
        private function checkToken() {
            $token = '';
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                if (strpos('Bearer', $_SERVER['HTTP_AUTHORIZATION']) !== 0) return;
                $token = $_SERVER['HTTP_AUTHORIZATION'];
            }
            else if (!empty($_SERVER['HTTP_CORE2M'])) {
                $token = $_SERVER['HTTP_CORE2M'];
            }

            if ($token) {
                Zend_Registry::set('auth', new StdClass()); //Необходимо для правильной работы контроллера
                $this->setContext('webservice');

                $this->checkWebservice();

                $webservice_controller = new ModWebserviceController();
                return $webservice_controller->dispatchWebToken($token);
            }
        }

        /**
         * Проверка на наличие и работоспособноси модуля Webservice
         */
        private function checkWebservice() {
            if ( ! $this->isModuleActive('webservice')) {
                \Core2\Error::catchJsonException(array('message' => $this->translate->tr('Модуль Webservice не активен')), 503);
            }

            $webservice_controller_path = $this->getModuleLocation('webservice') . '/ModWebserviceController.php';

            if ( ! file_exists($webservice_controller_path)) {
                \Core2\Error::catchJsonException(array('message' => $this->translate->tr('Модуль Webservice не существует')), 500);
            }

            require_once($webservice_controller_path);

            if ( ! class_exists('ModWebserviceController')) {
                \Core2\Error::catchJsonException(array('message' => $this->translate->tr('Модуль Webservice сломан')), 500);
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
                return $this->cli();
            }

            // Веб-сервис (SOAP)
            $matches = array();
            if ($this->is_soap || preg_match('~^(wsdl_([a-zA-Z0-9_]+)\.xml|ws_([a-zA-Z0-9_]+)\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {
                $this->setContext('webservice');

                $this->checkWebservice();

                if (isset($matches[2]) && $matches[2]) {
                    $service_request_action = 'wsdl';
                    $module_name = strtolower($matches[2]);
                } else {
                    $service_request_action = 'server';
                    $module_name = strtolower($matches[3]);
                }

                $webservice_controller = new ModWebserviceController();
                return $webservice_controller->dispatchSoap($module_name, $service_request_action);
            }

            // Веб-сервис (REST)
            $matches = array();
            if ($this->is_rest || preg_match('~api/([a-zA-Z0-9_]+)(?:/|)([^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {

                $this->setContext('webservice');

                $this->checkWebservice();

                if ( ! empty($matches[2])) {
                    if (strpos($matches[2], '/')) {
                        $path   = explode('/', $matches[2]);
                        $action = implode('', array_map('ucfirst', $path));
                    } else {
                        $action = ucfirst(strtolower($matches[2]));
                    }
                } else {
                    $action = 'Index';
                }

                require_once 'core2/inc/Interfaces/Delete.php'; //FIXME delete me

                $webservice_controller = new ModWebserviceController();
                return $webservice_controller->dispatchRest(strtolower($matches[1]), $action);
            }

            // Billing
            if ($this->isModuleActive('billing') &&
                (empty($_GET['module']) ||
                $_GET['module'] != 'billing' ||
                empty($_POST['system_name']) ||
                empty($_POST['type_operation']))
            ) {
                $this->setContext('billing');

                $billing_location  = $this->getModuleLocation('billing');
                $billing_page_path = $billing_location . '/classes/Billing_Disable.php';

                if ( ! file_exists($billing_page_path)) {
                    throw new Exception("File '{$billing_page_path}' does not exists");
                }

                require_once($billing_page_path);

                if ( ! class_exists('Billing_Disable')) {
                    throw new Exception("Class Billing_Disable does not exists");
                }

                $billing_disable = new Billing_Disable();
                if ($billing_disable->isDisable()) {
                    return $billing_disable->getDisablePage();
                }
            }

            // Парсим маршрут
            $this->routeParse();
            if (!empty($this->auth->ID) && !empty($this->auth->NAME) && is_int($this->auth->ID)) {
                // LOG USER ACTIVITY
                $logExclude = array('module=profile&unread=1'); //Запросы на проверку не прочитанных сообщений не будут попадать в журнал запросов
                $this->logActivity($logExclude);
                //TODO CHECK DIRECT REQUESTS except iframes

                require_once 'core2/inc/classes/Acl.php';
                require_once 'core2/inc/Interfaces/Delete.php';
                require_once 'core2/inc/Interfaces/File.php';
                // SETUP ACL
                $this->acl = new Acl();
                $this->acl->setupAcl();
            }
            else {
                // GET LOGIN PAGE
                if (!empty($_POST['xjxr']) || array_key_exists('X-Requested-With', Tool::getRequestHeaders())) {
                    throw new Exception('expired');
                }
                return $this->getLogin();
            }

            //$requestDir = str_replace("\\", "/", dirname($_SERVER['REQUEST_URI']));
            if (
                empty($_GET['module']) &&
                ($_SERVER['REQUEST_URI'] == $_SERVER['SCRIPT_NAME'] ||
                trim($_SERVER['REQUEST_URI'], '/') == trim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'])), '/'))
            ) {
                return $this->getMenu();
            } else {
                if ($this->deleteAction()) return;
                if (empty($_GET['module'])) throw new Exception($this->translate->tr("Модуль не найден"), 404);
                $module = strtolower($_GET['module']);
                $action = empty($_GET['action']) ? 'index' : strtolower($_GET['action']);
                $this->setContext($module, $action);

                if ($this->fileAction()) return;

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
                        throw new Exception($this->translate->tr("Субмодуль не существует"));
                    }

                } else {
                    if ($action == 'index') {
                        if (!$this->acl->checkAcl($module, 'access')) {
                            throw new Exception(911);
                        }
                        $_GET['action'] = "index";
                        if (!$this->isModuleActive($module)) throw new Exception($this->translate->tr("Модуль не существует"), 404);
                    } else {
                        $submodule_id = $module . '_' . $action;
                        $mods = $this->getSubModule($submodule_id);
                        if (!$mods) throw new Exception($this->translate->tr("Субмодуль не существует"), 404);
                        if ($mods['sm_id'] && !$this->acl->checkAcl($submodule_id, 'access')) {
                            throw new Exception(911);
                        }
                    }
                    if (empty($mods['sm_path'])) {
                        $location = $this->getModuleLocation($module); //определяем местоположение модуля
                        if ($this->translate->isSetup()) {
                            $this->translate->setupExtra($location);
                        }

                        if ($this->auth->MOBILE) {
                            $modController = "Mobile" . ucfirst(strtolower($module)) . "Controller";
                        } else {
                            $modController = "Mod" . ucfirst(strtolower($module)) . "Controller";
                        }
                        $autoload = $location . "/vendor/autoload.php";
                        if (file_exists($autoload)) {
                            require_once $autoload;
                        }
                        $this->requireController($location, $modController);
                        $modController = new $modController();
                        $action = "action_" . $action;
                        if (method_exists($modController, $action)) {
                            return $modController->$action();
                        } else {
                            throw new Exception($this->translate->tr("Метод не существует"), 404);
                        }
                    } else {
                        header("Location: " . $mods['sm_path']);
                    }
                }
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
         * Получение логотипа системы из conf.ini
         * или установка логотипа по умолчанию
         * @return string
         */
        private function getSystemLogo() {
            $res = $this->config->system->logo;
            if (!empty($res) && is_file($res)) {
                return $res;
            } else {
                return 'core2/html/' . THEME . '/img/logo.gif';
            }
        }

        /**
         * Форма входа в систему
         */
        protected function getLogin() {

            if (isset($_POST['action'])) {
                require_once 'core2/inc/CoreController.php';
                $this->setContext('admin');
                $core = new CoreController();
                $core->action_login($_POST);
                return;
            }
            $tpl = new Templater2();
            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.tpl");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/index.tpl");
            }

            $tpl->assign('{system_name}', $this->getSystemName());
            $tpl2 = new Templater2("core2/html/" . THEME . "/login/login.tpl");

            $errorNamespace = new Zend_Session_Namespace('Error');
            $blockNamespace = new Zend_Session_Namespace('Block');
            if (!empty($blockNamespace->blocked)) {
                $tpl2->error->assign('[ERROR_MSG]', $errorNamespace->ERROR);
                $tpl2->assign('[ERROR_LOGIN]', '');
            } elseif (!empty($errorNamespace->ERROR)) {
                $tpl2->error->assign('[ERROR_MSG]', $errorNamespace->ERROR);
                $tpl2->assign('[ERROR_LOGIN]', $errorNamespace->TMPLOGIN);
                $errorNamespace->ERROR = '';
            } else {
                $tpl2->error->assign('[ERROR_MSG]', '');
                $tpl2->assign('[ERROR_LOGIN]', '');
            }
            $config = Zend_Registry::get('config');
            if (empty($config->ldap->active) || !$config->ldap->active) {
                $tpl2->assign('<form', "<form onsubmit=\"document.getElementById('gfhjkm').value=hex_md5(document.getElementById('gfhjkm').value)\"");
            }
            $logo = $this->getSystemLogo();
            if (is_file($logo)) {
                $tpl2->logo->assign('{logo}', $logo);
            }
            $u = crypt(uniqid(), microtime());
            $tokenNamespace = new Zend_Session_Namespace('Token');
            $tokenNamespace->TOKEN = $u;
            $tokenNamespace->setExpirationHops(1);
            $tokenNamespace->lock();
            $tpl2->assign('name="action"', 'name="action" value="' . $u . '"');
            $tpl->assign('<!--index -->', $tpl2->parse());
            return $tpl->parse();
        }

        /**
         * Проверка удаления с последующей переадресацией
         * Если запрос на удаление корректен, всегда должно возвращать true
         *
         * @return bool
         * @throws Exception
         */
        private function deleteAction() {
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') return false;
            parse_str($_SERVER['QUERY_STRING'], $params);
            if (!empty($params['res']) && !empty($params['id'])) {
                header('Content-type: application/json; charset="utf-8"');
                $sess       = new Zend_Session_Namespace('List');
                $resource   = $params['res'];
                $sessData   = $sess->$resource;
                $loc = isset($sessData['loc']) ? $sessData['loc'] : '';
                if (!$loc) throw new Exception($this->translate->tr("Не удалось определить местоположение данных."), 13);
                parse_str($loc, $temp);
                $this->setContext($temp['module']);
                if ($temp['module'] !== 'admin') {
                    $module          = $temp['module'];
                    $location        = $this->getModuleLocation($module); //определяем местоположение модуля
                    $modController   = "Mod" . ucfirst(strtolower($module)) . "Controller";
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
         * Проверка наличия и целостности файла контроллера
         * @param $location - путь до файла
         * @param $modController - название файла контроллера
         *
         * @throws Exception
         */
        private function requireController($location, $modController) {
            $controller_path = $location . "/" . $modController . ".php";
            if (!file_exists($controller_path)) {
                throw new Exception($this->translate->tr("Модуль не существует"), 404);
            }
            require_once $controller_path; // подлючаем контроллер
            if (!class_exists($modController)) {
                throw new Exception($this->translate->tr("Модуль сломан"), 500);
            }
        }

        /**
         * Create the top menu
         * @return mixed|string
         */
        private function getMenu() {
            if ($this->auth->MOBILE) { //если core2m
                return $this->getMenuMobile();
            }
            //require_once("core2/ext/xajax_0.5_minimal/xajax_core/xajax.inc.php");
            $xajax = new xajax();
            //$xajax->configure("debug", true);
            //$xajax->configure('javascript URI', 'core2/ext/xajax_0.5_minimal/');
            $xajax->configure('javascript URI', 'core2/vendor/xajax/xajax');
            $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
            //$xajax->registerFunction('post');
            $xajax->processRequest();

            $mods   = $this->getModuleList();
            $tpl    = new Templater2();
            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/indexMobile2.tpl");
                $tpl2 = new Templater2("core2/html/" . THEME . "/menuMobile.tpl");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/index2.tpl");
                $tpl2 = new Templater2("core2/html/" . THEME . "/menu.tpl");
            }
            $tpl->assign('{system_name}', $this->getSystemName());

            $tpl2->assign('<!--SYSTEM_NAME-->',        $this->getSystemName());
            $tpl2->assign('<!--CURRENT_USER_LOGIN-->', htmlspecialchars($this->auth->NAME));
            $tpl2->assign('<!--CURRENT_USER_FN-->',    htmlspecialchars($this->auth->FN));
            $tpl2->assign('<!--CURRENT_USER_LN-->',    htmlspecialchars($this->auth->LN));
            $tpl2->assign('[GRAVATAR_URL]',            "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))));


            $modtpl = $tpl2->getBlock('modules');
            $html   = "";
            $js     = array();
            foreach ($mods as $data) {
                if (!empty($data['sm_key'])) continue;
                $module_id = $data['module_id'];

                if ($data['isset_home_page'] == 'N') {
                    $first_action = 'index';
                    foreach ($mods as $mod) {
                        if ( ! empty($mod['sm_id']) && $data['m_id'] == $mod['m_id']) {
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

                $html .= str_replace(
                    array('[MODULE_ID]', '[MODULE_NAME]', '[MODULE_ACTION]', '[MODULE_URL]'),
                    array($module_id, $data['m_name'], $module_action, $url),
                    $modtpl
                );
                if ($module_id == 'admin') continue;
                $location      = $this->getModuleLocation($module_id); //получение расположения модуля
                $modController = "Mod" . ucfirst($module_id) . "Controller";
                $file_path     = $location . "/" . $modController . ".php";
                if (file_exists($file_path)) {
                    ob_start();
                    require_once $file_path;
                    if (class_exists($modController)) { // подключаем класс модуля
                        $this->setContext($module_id);
                        $modController = new $modController();
                        if (method_exists($modController, 'topJs')) {
                            if ($modEvent = $modController->topJs()) {
                                $js = array_merge($js, $modEvent);
                            }
                        }
                    }
                    ob_clean();
                }
            }

            $modtpl = $tpl2->getBlock('submodules');
            $html2 = "";
            foreach ($mods as $data) {
                if (!empty($data['sm_key'])) {
                    $url = "index.php?module=" . $data['module_id'] . "&action=" . $data['sm_key'];
                    $html2 .= str_replace(array('[MODULE_ID]', '[SUBMODULE_ID]', '[SUBMODULE_NAME]', '[SUBMODULE_URL]'),
                                          array($data['module_id'], $data['sm_key'], $data['sm_name'], $url),
                                          $modtpl);
                }
            }
            $tpl->assign('<!--index-->', $tpl2->parse());
            $out = '';
            if ($js) {
                foreach ($js as $src) {
                    $out .= '<script type="text/javascript" language="javascript" src="' . $src . '"></script>';
                }
            }
            $tpl->assign('<!--xajax-->', "<script type=\"text/javascript\" language=\"javascript\">var coreTheme='" . THEME . "'</script>" . $xajax->getJavascript() . $out);
            $html = str_replace("<!--modules-->",    $html,  $tpl->parse());
            $html = str_replace("<!--submodules-->", $html2, $html);
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
                    'isset_home_page' => empty($module['isset_home_page']) ? 'Y' : $module['isset_home_page']
                );
                foreach ($data as $submodule) {
                    if (empty($submodule['sm_id'])) continue;
                    $mods[] = $submodule;
                }
            }
            if ($this->auth->ADMIN || $this->auth->NAME == 'root') {
                $tmp = array('m_id' => -1, 'm_name' => $this->translate->tr('Админ'), 'module_id' => 'admin', 'isset_home_page' => 'Y');
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
         * Cli
         * @return string
         * @throws Exception
         */
        private function cli() {

            // Модуль cron работает только начиная с версии 2.3.0

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
	                "\t-m\t--module\tModule name",
	                "\t-a\t--action\tCli method name",
	                "\t-p\t--param\t\tParameter in method",
	                "\t-s\t--section\tSection name in config file",
					"\t-h\t--help\t\tHelp info",
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

	                $mod_path = $this->getModuleLocation($module);
	                $mod_controller = 'Mod' . ucfirst(strtolower($module)) . 'Controller';
	                $controller_path = "{$mod_path}/{$mod_controller}.php";

	                if ( ! file_exists($controller_path)) {
	                    throw new Exception(sprintf($this->translate->tr("File controller '%s' does not exists"), $controller_path));
	                }

	                require_once $controller_path;

	                if ( ! class_exists($mod_controller)) {
	                    throw new Exception(sprintf($this->translate->tr("Class controller '%s' not found"), $mod_controller));
	                }

	                $mod_methods = get_class_methods($mod_controller);
	                $cli_method = 'cli' . ucfirst($action);
	                if ( ! array_search($cli_method, $mod_methods)) {
	                    throw new Exception(sprintf($this->translate->tr("Cli method '%s' not found in controller '%s'"), $cli_method, $mod_controller));
	                }

	                $mod_instance = new $mod_controller();
	                $result = call_user_func_array(array($mod_instance, $cli_method), $params);

	                if (is_scalar($result)) {
	                    return (string)$result . PHP_EOL;
	                }

	            } catch (Exception $e) {
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
            $temp  = explode("/", str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'])));
            $temp2 = explode("/", $_SERVER['REQUEST_URI']);

            $i = -1;
            foreach ($temp as $k => $v) {
                if ($temp2[$k] == $v) {
                    $i++;
                    unset($temp2[$k]);
                }
            }

            if (count($temp2) > 1) {
                $i = 0;
                foreach ($temp2 as $k => $v) {
                    if ($i == 0) $_GET['module'] = $v;
                    elseif ($i == 1) $_GET['action'] = $v;
                    else {
                        if (!ceil($i%2)) {
                            $v = explode("?", $v);
                            if (isset($v[1])) {
                                $_GET[$v[0]] = '';
                                break;
                            } else {
                                if (isset($temp2[$k + 1])) {
                                    $vv          = explode("?", $temp2[$k + 1]);
                                    $_GET[$v[0]] = $vv[0];
                                    if (isset($vv[1])) {
                                        break;
                                    }
                                } else {
                                    $_GET[$v[0]] = '';
                                    break;
                                }
                            }
                        }
                    }
                    $i++;
                }
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
                    $modController = new $modController();
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
         */
        private function getMenuMobile() {
            header('Content-type: application/json; charset="utf-8"');
            $mods   = $this->getModuleList();
            $modsList = array();
            foreach ($mods as $data) {
                $modsList[$data['m_id']] = array('module_id'  => $data['module_id'],
                                                 'm_name'     => $data['m_name'],
                                                 'm_id'     => $data['m_id'],
                                                 'submodules' => array());
            }
            foreach ($mods as $data) {
                if (!empty($data['sm_id'])) {
                    $modsList[$data['m_id']]['submodules'][] = array('sm_id'   => $data['sm_id'],
                                                                     'sm_key'  => $data['sm_key'],
                                                                     'sm_name' => $data['sm_name']);
                }
            }
            //проверяем наличие контроллера для core2m в модулях
            foreach ($modsList as $k => $data) {
                $location = $this->getModuleLocation($data['module_id']);
                $modController = "Mobile" . ucfirst(strtolower($data['module_id'])) . "Controller";
                if (!file_exists($location . "/$modController.php")) {
                    unset($modsList[$k]);
                }
            }
            $modsList = array('system_name' => $this->getSystemName(),
                              'login'       => $this->auth->NAME,
                              'avatar'      => "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))),
                              'modules'     => $modsList);
            return json_encode($modsList);
        }

        /**
         * Установка контекста выполнения скрипта
         * @param string $module
         * @param string $action
         */
        private function setContext($module, $action = 'index') {
            Zend_Registry::set('context', array($module, $action));
        }
    }


    /**
     * Какой-то пост
     *
     * @param string $func
     * @param string $loc
     * @param array  $data
     *
     * @return xajaxResponse
     * @throws Exception
     * @throws Zend_Exception
     */
    function post($func, $loc, $data) {

        $translate = Zend_Registry::get('translate');
        $res       = new xajaxResponse();
        $loc       = explode('?', $loc);

        if (isset($loc[1])) {
            unset($loc[0]);
            $loc = implode('?', $loc);
        } else {
            $loc = $loc[0];
        }

        parse_str($loc, $params);
        if (empty($params['module'])) throw new Exception($translate->tr("Модуль не найден"), 404);

        $acl = new Acl();

        Zend_Registry::set('context', array($params['module'], !empty($params['action']) ? $params['action'] : 'index'));

        if ($params['module'] == 'admin') {
            require_once DOC_ROOT . 'core2/mod/ModAjax.php';
            $auth = Zend_Registry::get('auth');
            if ( ! $auth->ADMIN) throw new Exception(911);

            $xajax = new ModAjax($res);
            if (method_exists($xajax, $func)) {
                $xajax->setupAcl();
                try {
                    return $xajax->$func($data);
                } catch (Exception $e) {
                    \Core2\Error::catchXajax($e, $res);
                }
            } else {
                throw new Exception($translate->tr("Метод не найден"), 60);
            }

        } else {
            if (empty($params['action']) || $params['action'] == 'index') {
                if ( ! $acl->checkAcl($params['module'], 'access')) {
                    throw new Exception(911);
                }
                $params['action'] = 'index';
            } else {
                if ( ! $acl->checkAcl($params['module'] . '_' . $params['action'], 'access')) {
                    throw new Exception(911);
                }
            }

            $db        = new Db;
            $location  = $db->getModuleLocation($params['module']);
            $file_path = $location . "/ModAjax.php";

            if (file_exists($file_path)) {
                require_once $file_path;
                $xajax = new ModAjax($res);
                $func = 'ax' . ucfirst($func);
                if (method_exists($xajax, $func)) {
                    try {
                        $data['params'] = $params;
                        return $xajax->$func($data);
                    } catch (Exception $e) {
                        \Core2\Error::catchXajax($e, $res);
                    }
                } else {
                    throw new Exception($translate->tr("Метод не найден"), 60);
                }
            }
        }

        return $res;
    }