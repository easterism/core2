<?
    header('Content-Type: text/html; charset=utf-8');

    // Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
    define("DOC_ROOT", dirname(str_replace("//", "/", $_SERVER['SCRIPT_FILENAME'])) . "/");
    define("DOC_PATH", substr(DOC_ROOT, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))) ? : '/');

    $conf_file = DOC_ROOT . "core2/vendor/autoload.php";
    if (!file_exists($conf_file)) {
        \Core2\Error::Exception("Composer autoload is missing.");
    }

    require_once($conf_file);
    require_once("Error.php");

    use Zend\Cache\StorageFactory;
    use Zend\Session\Config\SessionConfig;
    use Zend\Session\SessionManager;
    use Zend\Session\SaveHandler\Cache AS SessionHandlerCache;
    use Zend\Session\Container as SessionContainer;

    $conf_file = DOC_ROOT . "conf.ini";

    if (!file_exists($conf_file)) {
        \Core2\Error::Exception("conf.ini is missing.");
    }
    $config = array(
        'system' => array('name' => 'CORE'),
        'include_path' => '',
        'temp' => getenv('TMP'),
        'debug' => array('on' => false),
        'session' => array('cookie_httponly' => true,
                        'use_only_cookies' => true),
        'database' => array(
            'adapter' => 'Pdo_Mysql',
            'params' => array(
                'charset' => 'utf8'
            ),
            'driver_options' => [
                \PDO::ATTR_TIMEOUT => 3
            ],
            'isDefaultTableAdapter' => true,
            'profiler' => array(
                'enabled' => false,
                'class' => 'Zend_Db_Profiler_Firebug'
            ),
            'caseFolding' => true,
            'autoQuoteIdentifiers' => true,
            'allowSerialization' => true,
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

        $section = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';
        $config2 = new Zend_Config_Ini($conf_file, $section);
        $conf_d = DOC_ROOT . "conf.ext.ini";
        if (file_exists($conf_d)) {
            $config2->merge(new Zend_Config_Ini($conf_d, $section));
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

    //проверяем настройки для базы данных
    if ($config->database->adapter === 'Pdo_Mysql') {
        $config->database->params->adapterNamespace = 'Core_Db_Adapter';
        //подключаем собственный адаптер базы данных
        require_once($config->database->params->adapterNamespace . "_{$config->database->adapter}.php");
    } elseif ($config->database->adapter === 'Pdo_Pgsql') {
        $config->database->params->adapterNamespace = 'Zend_Db_Adapter';
        $config->database->schema = $config->database->params->dbname;
        $config->database->params->dbname = $config->database->pgname ? $config->database->pgname : 'postgres';
    }

    //конфиг стал только для чтения
    $config->setReadOnly();

    if (isset($config->include_path) && $config->include_path) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
    }

    //подключаем мультиязычность
    require_once 'I18n.php';
    $translate = new \Core2\I18n($config);

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
        $sessHandler = '';
        if ($config->session->saveHandler) {
            $options = ['namespace' => 'phpsess'];
            if ($config->session->remember_me_seconds) $options['ttl'] = $config->session->remember_me_seconds;
            if ($config->session->savePath) $options['server'] = $config->session->savePath;

            if ($config->session->saveHandler === 'memcache') {
                $cache = StorageFactory::factory(array(
                    'adapter' => array(
                        'name' => 'memcached',
                        'options' => $options
                    )
                ));
                $sessHandler = new SessionHandlerCache($cache);
            }
            elseif ($config->session->saveHandler === 'redis') {
                $cache = StorageFactory::factory([
                    'adapter' => [
                        'name'    => 'redis',
                        'options' => $options
                    ]
                ]);
                $sessHandler = new SessionHandlerCache($cache);
            }
        }
        $sess_config = new SessionConfig();
        $sess_config->setOptions($config->session);
        $sess_manager = new SessionManager($sess_config);
        if ($sessHandler) $sess_manager->setSaveHandler($sessHandler);
        //сохраняем менеджер сессий
        SessionContainer::setDefaultManager($sess_manager);
    }

	//сохраняем конфиг
	Zend_Registry::set('config', $config);

    //обрабатываем конфиг ядра
    $core_conf_file = __DIR__ . "/../../conf.ini";
    if (file_exists($core_conf_file)) {
        $core_config = new Zend_Config_Ini($core_conf_file, 'production');
        Zend_Registry::set('core_config', $core_config);
    }

	require_once 'Zend_Session_Namespace.php'; //DEPRECATED
	require_once 'Db.php';
	require_once 'Common.php';
	require_once 'Templater.php'; //DEPRECATED
	require_once 'Templater2.php'; //DEPRECATED
	require_once 'Templater3.php';


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

            // проверяем, есть ли в запросе токен
            $auth = $this->checkToken();
            if ($auth) { //произошла авторизация по токену
                $this->auth = $auth;
                Zend_Registry::set('auth', $this->auth); //DEPRECATED
                return; //выходим, если авторизация состоялась
            }

            $this->detectWebService();
            $this->auth = new StdClass();
            if ($this->is_rest || $this->is_soap) {
                Zend_Registry::set('auth', $this->auth); //DEPRECATED
                return;
            }
            if (PHP_SAPI === 'cli') {
                $this->is_cli = true;
                Zend_Registry::set('auth', $this->auth);  //DEPRECATED
                return;
            }

            $this->auth 	= new SessionContainer('Auth');
            if (!empty($this->auth->ID) && $this->auth->ID > 0) {
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
                Zend_Registry::set('auth', $this->auth);
            }
            else {
                $this->auth->TOKEN = md5($_SERVER['HTTP_HOST'] . $_SERVER['HTTP_USER_AGENT']);
            }
            Zend_Registry::set('auth', $this->auth); // сохранение сессии в реестре   //DEPRECATED
            //if (empty($_POST)) $this->auth->getManager()->writeClose(); // закрываем сессию для записи
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

                require_once DOC_ROOT . 'core2/inc/Interfaces/Delete.php'; //FIXME delete me
                $route = $this->routeParse();

                $webservice_controller = new ModWebserviceController();

                $version = $matches['version'];
                $method  = '';

                $action_explode = explode('/', $matches['action']);
                foreach ($action_explode as $action_part) {
                    $method .= ucfirst($action_part);
                }

                $method = lcfirst($method);

                return $webservice_controller->dispatchRest($route['module'], $method, $version); //TODO сделать через DI
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

                // LOG USER ACTIVITY
                $logExclude = array('module=profile&unread=1'); //TODO Запросы на проверку не прочитанных сообщений не будут попадать в журнал запросов
                $this->logActivity($logExclude);
                //TODO CHECK DIRECT REQUESTS except iframes

                require_once 'core2/inc/classes/Acl.php';
                require_once 'core2/inc/Interfaces/Delete.php';
                require_once 'core2/inc/Interfaces/File.php';
                require_once 'core2/inc/Interfaces/Subscribe.php';
                // SETUP ACL
                $this->acl = new \Core2\Acl();
                $this->acl->setupAcl();

                if ($you_need_to_pay = $this->checkBilling()) return $you_need_to_pay;
            }
            else {
                if (isset($_GET['core'])){
                    if ($_GET['core'] == 'registration'){
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                            return $this->getRegistration();

                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            return $this->registration();

                        } else {
                            http_response_code(404);
                            return '';
                        }
                    }

                    if ($_GET['core'] == 'registration_complete') {
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                            if (empty($_GET['key'])){
                                http_response_code(404);
                                return '';
                            }
                            return $this->getRegistrationConfirm();

                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            return $this->setUserPass();

                        } else {
                            http_response_code(404);
                            return '';
                        }
                    }

                    if ($_GET['core'] == 'restore') {
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

                            if (empty($_GET['key'])) {
                                return $this->getRestore();
                            } else {
                                return $this->restoreConfirm();
                            }

                        } else {
                            http_response_code(404);
                            return '';
                        }
                    }

                    if ($_GET['core'] == 'restore_complete') {
                        return $this->setUserRestorePass();
                    }

                }
                // GET LOGIN PAGE
                if (array_key_exists('X-Requested-With', Tool::getRequestHeaders())) {
                    if ( ! empty($_POST['xjxr'])) {
                        throw new Exception('expired');
                    }
                    if ( ! empty($_GET['module'])) {
                        http_response_code(403);
                        return '';
                    }
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
                if ($this->deleteAction()) return '';

                $module = $route['module'];
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
                        throw new Exception($this->translate->tr("Субмодуль не существует") . ": " . $action);
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

                        if ($this->auth->MOBILE) {
                            $modController = "Mobile" . ucfirst(strtolower($module)) . "Controller";
                        } else {
                            $modController = "Mod" . ucfirst(strtolower($module)) . "Controller";
                        }

                        $this->requireController($location, $modController);
                        $modController = new $modController();
                        $action = "action_" . $action;
                        if (method_exists($modController, $action)) {
                            $out = $modController->$action();

                            return $out;
                        } else {
                            throw new Exception(sprintf($this->translate->tr("Метод %s не существует"), $action), 404);
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

            if ($this->config->system->profile && $this->config->system->profile->on) {
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
         * Форма входа в систему
         * @return string
         * @throws Zend_Exception
         */
        protected function getLogin() {

            if (isset($_POST['action']) && ! $this->auth->ID) {
                require_once 'core2/inc/CoreController.php';
                $this->setContext('admin');
                $core = new CoreController();
                $url  = "index.php";
                if ($core->action_login($_POST)) {
                    if ( ! empty($_SERVER['QUERY_STRING'])) {
                        $url .= "#" . $_SERVER['QUERY_STRING'];
                    }
                }
                header("Location: $url");
                return '';
            }
            $tpl = new Templater2();
            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.html");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/index.html");
            }

            $tpl->assign('{system_name}', $this->getSystemName());
            $tpl2 = new Templater2("core2/html/" . THEME . "/login/login.html");

            $errorNamespace = new SessionContainer('Error');
            $blockNamespace = new SessionContainer('Block');

            if ( ! empty($blockNamespace->blocked)) {
                $tpl2->error->assign('[ERROR_MSG]', $errorNamespace->ERROR);
                $tpl2->assign('[ERROR_LOGIN]', '');
            } elseif ( ! empty($errorNamespace->ERROR)) {
                $tpl2->error->assign('[ERROR_MSG]', $errorNamespace->ERROR);
                $tpl2->assign('[ERROR_LOGIN]', $errorNamespace->TMPLOGIN);
                $errorNamespace->ERROR = '';
            } else {
                $tpl2->error->assign('[ERROR_MSG]', '');
                $tpl2->assign('[ERROR_LOGIN]', '');
            }
            $config = Zend_Registry::get('config');

            if (empty($config->ldap->active) || ! $config->ldap->active) {
                $tpl2->assign('<form', "<form onsubmit=\"document.getElementById('gfhjkm').value=hex_md5(document.getElementById('gfhjkm').value)\"");
            }

            $logo = $this->getSystemLogo();

            if (is_file($logo)) {
                $tpl2->logo->assign('{logo}', $logo);
            }
            if ( ! empty($this->auth->TOKEN)) {
                $tpl2->assign('name="action"', 'name="action" value="' . $this->auth->TOKEN . '"');
            }

            if ($this->config->mail && $this->config->mail->server) {
                if ($this->core_config->registry->active == 'Y') {
                    $tpl2->touchBlock('registration');
                }

                if ($this->core_config->registry->restore == 'Y') {
                    $tpl2->touchBlock('restore');
                }
            }

            $favicon = $this->getSystemFavicon();

            $tpl->assign('favicon.png', isset($favicon['png']) && is_file($favicon['png']) ? $favicon['png'] : '');
            $tpl->assign('favicon.ico', isset($favicon['ico']) && is_file($favicon['ico']) ? $favicon['ico'] : '');

            $tpl->assign('<!--index -->', $tpl2->parse());
            return $tpl->parse();
        }


        /**
         * Форма Регистрации
         * @return string
         * @throws Zend_Exception
         */
        protected function getRegistration() {

            $tpl = new Templater2();
            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.html");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/index.html");
            }

            $tpl->assign('{system_name}', $this->getSystemName());
            $tpl2 = new Templater2("core2/html/" . THEME . "/login/RegistryUser.html");

            $logo = $this->getSystemLogo();

            if (is_file($logo)) {
                $tpl2->logo->assign('{logo}', $logo);
            }
            if ( ! empty($this->auth->TOKEN)) {
                $tpl2->assign('name="action"', 'name="action" value="' . $this->auth->TOKEN . '"');
            }

            $favicon = $this->getSystemFavicon();

            $tpl->assign('favicon.png', isset($favicon['png']) && is_file($favicon['png']) ? $favicon['png'] : '');
            $tpl->assign('favicon.ico', isset($favicon['ico']) && is_file($favicon['ico']) ? $favicon['ico'] : '');

            $tpl->assign('<!--index -->', $tpl2->parse());
            return $tpl->parse();
        }


        /**
         * @return false|string
         * @throws Exception
         */
        protected function registration() {

            $login   = trim($_POST['email']);
            $db      = $this->getConnection($this->config->database);
            $u_id    = $this->dataUsers->fetchRow($this->db->quoteInto("u_login = ?", $login));
            $u_email = $this->dataUsers->fetchRow($this->db->quoteInto("email = ?", $_POST['email']));

            if ($u_id) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Такой пользователь уже есть'
                ]);
            }

            if ($u_email) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Такой Email уже есть'
                ]);
            }

            if (empty($login)) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Поле Email не заполнено'
                ]);
            }

            if (empty($_POST['unp'])) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Поле УНП не заполнено'
                ]);
            }


            $contractor = $db->fetchRow("
                SELECT email,
                       active_sw
                FROM mod_ordering_contractors
                WHERE email = ?
            ", $_POST['email']);

            if ( ! empty($contractor)) {
                if ($contractor['active_sw'] == 'N') {
                    $reg_key = Tool::pass_salt(md5($_POST['email'] . microtime()));
                    $where   = $db->quoteInto('email = ?', $contractor['email']);
                    $db->update('mod_ordering_contractors', [
                        'reg_key'      => $reg_key,
                        'date_expired' => new Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
                    ], $where);

                    $this->sendEmailRegistration($_POST['email'], $reg_key);

                    return json_encode([
                        'status'  => 'success',
                        'message' => 'На указанную вами почту отправлены данные для входа в систему.'
                    ]);

                } else {
                    return json_encode([
                        'status'  => 'error',
                        'message' => 'Такой контрагент уже есть'
                    ]);
                }
            }

            $reg_key = Tool::pass_salt(md5($_POST['email'] . microtime()));
            $db->insert('mod_ordering_contractors', [
                'title'        => $_POST['company_name'],
                'email'        => $_POST['email'],
                'unp'          => $_POST['unp'],
                'phone'        => $_POST['tel'],
                'reg_key'      => $reg_key,
                'date_expired' => new Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)'),
            ]);

            $this->sendEmailRegistration($_POST['email'], $reg_key);

            return json_encode([
                'status' => 'success'
            ]);
        }


        /**
         * @param $mail_address
         * @param $reg_key
         */
        private function sendEmailRegistration($mail_address, $reg_key) {

            $protocol = ! empty($this->config->system) && $this->config->system->https ? 'https' : 'http';
            $host     = ! empty($this->config->system) ? $this->config->system->host : '';

            $content_email = "
                Вы зарегистрированы на сервисе {$host}<br>
                Для продолжения регистрации <b>перейдите по указанной ниже ссылке</b>.<br><br>
                <a href=\"{$protocol}://{$host}/index.php?core=registration_complete&key={$reg_key}\" 
                   style=\"font-size: 16px\">{$protocol}://{$host}/index.php?core=registration_complete&key={$reg_key}</a>
            ";

            $reg = Zend_Registry::getInstance();
            $reg->set('context', ['queue', 'index']);

            require_once 'Email.php';
            $email = new \Core2\Email();
            $email->to($mail_address)
                ->subject("Автопромсервис: Регистрация на сервисе")
                ->body($content_email)
                ->send(true);
        }


        /**
         * @return string
         * @throws Exception
         */
        protected function getRegistrationConfirm(){

            $db  = $this->getConnection($this->config->database);
            $tpl = new Templater2();

            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.html");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/index.html");
            }

            $tpl->assign('{system_name}', $this->getSystemName());

            $tpl2 = new Templater2("core2/html/" . THEME . "/login/ConfirmRegistryUser.html");

            $logo = $this->getSystemLogo();

            if (is_file($logo)) {
                $tpl2->logo->assign('{logo}', $logo);
            }
            $date_expired = $db->fetchRow("
                SELECT date_expired
                FROM mod_ordering_contractors
                WHERE reg_key = ?
                AND date_expired > NOW()
            ", $_GET['key']);

            if (empty($date_expired)) {
                $tpl2->error->assign('[ERROR_MSG]', 'Ссылка устарела');
            } else {
                $tpl2->pass->assign('[KEY]', $_GET['key']);
            }
            $favicon = $this->getSystemFavicon();

            $tpl->assign('favicon.png', isset($favicon['png']) && is_file($favicon['png']) ? $favicon['png'] : '');
            $tpl->assign('favicon.ico', isset($favicon['ico']) && is_file($favicon['ico']) ? $favicon['ico'] : '');

            $tpl->assign('<!--index -->', $tpl2->parse());
            return $tpl->parse();
        }


        /**
         * @return false|string
         * @throws Zend_Db_Adapter_Exception
         * @throws Zend_Db_Exception
         */
        protected function setUserPass() {

            $db = $this->getConnection($this->config->database);

            $user_info = $db->fetchRow("
                SELECT user_id,
                       id
                FROM mod_ordering_contractors 
                WHERE reg_key = ?
                LIMIT 1
            ", $_POST['key']);
            if ( ! empty($_POST['password'])) {
                $where = $db->quoteInto('id = ?', $user_info['id']);
                $db->update('mod_ordering_contractors', [
                    'reg_key'      => new Zend_Db_Expr('NULL'),
                    'date_expired' => new Zend_Db_Expr('NULL')
                ], $where);

                $where = $db->quoteInto('id = ?', $user_info['id']);
                $db->update('mod_ordering_contractors', [
                    'u_pass' => Tool::pass_salt($_POST['password']),
                ], $where);

                return json_encode([
                    'status'  => 'success',
                    'message' => '<h4>Готово!</h4>
                                  <p>Вы сможете зайти в систему, после прохождения модерации</p>'
                ]);

            } else {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Заполните поле пароль'

                ]);
            }
        }


        /**
         * @return false|string
         * @throws Zend_Db_Adapter_Exception
         * @throws Zend_Db_Exception
         */
        protected function setUserRestorePass() {

            $db = $this->getConnection($this->config->database);

            $user_info = $db->fetchRow("
                SELECT user_id,
                       id
                FROM mod_ordering_contractors 
                WHERE reg_key = ?
                LIMIT 1
            ", $_POST['key']);

            if ( ! empty($_POST['password'])) {
                $where = $db->quoteInto('id = ?', $user_info['id']);
                $db->update('mod_ordering_contractors', [
                    'reg_key'      => new Zend_Db_Expr('NULL'),
                    'date_expired' => new Zend_Db_Expr('NULL')
                ], $where);

                $where = $db->quoteInto('id = ?', $user_info['id']);
                $db->update('mod_ordering_contractors', [
                    'u_pass' => Tool::pass_salt($_POST['password']),
                ], $where);

                $where = $db->quoteInto('u_id = ?', $user_info['user_id']);
                $db->update('core_users', [
                    'u_pass' => Tool::pass_salt($_POST['password']),
                ], $where);

                return json_encode([
                    'status'  => 'success',
                    'message' => '<h4>Пароль изменен!</h4>
                                  <p>Вернитесь на форму входа и войдите в систему с новым паролем</p>'
                ]);
            } else {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Заполните поле пароль'
                ]);
            }

        }


        /**
         * @return false|string
         * @throws Zend_Db_Adapter_Exception
         * @throws Zend_Db_Exception
         */
        protected function getRestore() {

            if ( ! empty($_GET['email'])) {
                $db        = $this->getConnection($this->config->database);
                $user_info = $db->fetchRow("
                    SELECT u_id
                    FROM core_users 
                    WHERE email = ?
                    LIMIT 1
                ", $_GET['email']);

                if ( ! empty($user_info)) {
                    $reg_key = Tool::pass_salt(md5($_GET['email'] . microtime()));

                    $user_info = $db->fetchRow("
                        SELECT oc.user_id
                        FROM core_users AS u
                            LEFT JOIN mod_ordering_contractors AS oc ON u.u_id = oc.user_id
                        WHERE u.email = ?
                        LIMIT 1
                    ", $_GET['email']);

                    $where = $db->quoteInto('user_id = ?', $user_info['user_id']);
                    $db->update('mod_ordering_contractors', [
                        'reg_key'      => $reg_key,
                        'date_expired' => new Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
                    ], $where);


                    $protocol = ! empty($this->config->system) && $this->config->system->https ? 'https' : 'http';
                    $host     = ! empty($this->config->system) ? $this->config->system->host : '';

                    $content_email = "
                        Вы запросили смену пароля на сервисе {$host}<br>
                        Для продолжения <b>перейдите по указанной ниже ссылке</b>.<br><br>
        
                        <a href=\"{$protocol}://{$host}/index.php?core=restore&key={$reg_key}\" 
                           style=\"font-size: 16px\">{$protocol}://{$host}/index.php?core=restore&key={$reg_key}</a>
                    ";

                    $reg = Zend_Registry::getInstance();
                    $reg->set('context', ['queue', 'index']);

                    require_once 'Email.php';
                    $email = new \Core2\Email();
                    $email->to($_GET['email'])
                        ->subject("Автопромсервис: Восстановление пароля")
                        ->body($content_email)
                        ->send(true);

                    return json_encode([
                        'status'  => 'success',
                        'message' => 'На указанную вами почту отправлены данные для смены пароля'
                    ]);
                } else {
                    return json_encode([
                        'status'  => 'error',
                        'message' => 'Такого email нету в системе'
                    ]);
                }
            }

            $tpl = new Templater2();

            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.html");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/index.html");
            }

            $tpl->assign('{system_name}', $this->getSystemName());
            $tpl2 = new Templater2("core2/html/" . THEME . "/login/RestorePassUser.html");

            $logo = $this->getSystemLogo();

            if (is_file($logo)) {
                $tpl2->logo->assign('{logo}', $logo);
            }

            $favicon = $this->getSystemFavicon();

            $tpl->assign('favicon.png', isset($favicon['png']) && is_file($favicon['png']) ? $favicon['png'] : '');
            $tpl->assign('favicon.ico', isset($favicon['ico']) && is_file($favicon['ico']) ? $favicon['ico'] : '');

            $tpl->assign('<!--index -->', $tpl2->parse());
            return $tpl->parse();
        }


        /**
         * @return string
         * @throws Exception
         */
        protected function restoreConfirm() {

            $db  = $this->getConnection($this->config->database);
            $tpl = new Templater2();

            if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.html");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/login/index.html");
            }

            $tpl->assign('{system_name}', $this->getSystemName());

            $tpl2 = new Templater2("core2/html/" . THEME . "/login/ConfirmRestorePassUser.html");

            $logo = $this->getSystemLogo();

            if (is_file($logo)) {
                $tpl2->logo->assign('{logo}', $logo);
            }
            $date_expired = $db->fetchRow("
                SELECT date_expired
                FROM mod_ordering_contractors
                WHERE reg_key = ?
                  AND date_expired > NOW()
            ", $_GET['key']);

            if (empty($date_expired)) {
                $tpl2->error->assign('[ERROR_MSG]', 'Ссылка устарела');
            } else {
                $tpl2->pass->assign('[KEY]', $_GET['key']);
            }
            $favicon = $this->getSystemFavicon();

            $tpl->assign('favicon.png', isset($favicon['png']) && is_file($favicon['png']) ? $favicon['png'] : '');
            $tpl->assign('favicon.ico', isset($favicon['ico']) && is_file($favicon['ico']) ? $favicon['ico'] : '');

            $tpl->assign('<!--index -->', $tpl2->parse());
            return $tpl->parse();
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

            if (preg_match('~api/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d\.\d)(?:/)(?<action>[^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
                $this->is_rest = $matches;
                return;
            }
            // DEPRECATED
            if (preg_match('~api/([a-zA-Z0-9_]+)(?:/|)([^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {
                $this->is_rest = $matches;
                return;
            }
            // DEPRECATED
            if (preg_match('~^(wsdl_([a-zA-Z0-9_]+)\.xml|ws_([a-zA-Z0-9_]+)\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {
                $this->is_soap = $matches;
                return;
            }
            if (preg_match('~^soap/(?<module>[a-zA-Z0-9_]+)/v(?<version>\d\.\d)/(?<action>wsdl\.xml|service\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {
                $this->is_soap = $matches;
                return;
            }
        }


        /**
         * Проверка наличия токена в запросе
         * Только для запросов с авторизацией по токену!
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
            Zend_Registry::set('auth', new StdClass()); //Необходимо для правильной работы контроллера
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
         * Получение favicon системы из conf.ini
         * @return array
         */
        private function getSystemFavicon() {

            $favicon_png = $this->config->system->favicon_png;
            $favicon_ico = $this->config->system->favicon_ico;

            $favicon_png = $favicon_png && is_file($favicon_png)
                ? $favicon_png
                : (is_file('favicon.png') ? 'favicon.png' : 'core2/html/' . THEME . '/img/favicon.png');

            $favicon_ico = $favicon_ico && is_file($favicon_ico)
                ? $favicon_ico
                : (is_file('favicon.ico') ? 'favicon.ico' : 'core2/html/' . THEME . '/img/favicon.ico');


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
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') return false;
            parse_str($_SERVER['QUERY_STRING'], $params);
            if (!empty($params['res']) && !empty($params['id'])) {
                header('Content-type: application/json; charset="utf-8"');
                $sess       = new SessionContainer('List');
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
         *
         * @param $location - путь до файла
         * @param $modController - название файла контроллера
         *
         * @throws Exception
         */
        private function requireController($location, $modController) {
            $controller_path = $location . "/" . $modController . ".php";
            if (!file_exists($controller_path)) {
                throw new Exception($this->translate->tr("Модуль не существует") . ": " . $location, 404);
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

            //если core2m
            if ($this->auth->MOBILE) {
                return $this->getMenuMobile();
            }

            $xajax = new xajax();
            $xajax->configure('javascript URI', 'core2/vendor/belhard/xajax');
            $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
            $xajax->processRequest();



            if (Tool::isMobileBrowser()) {
                $tpl_file      = "core2/html/" . THEME . "/indexMobile2.tpl";
                $tpl_file_menu = "core2/html/" . THEME . "/menuMobile.tpl";
            } else {
                $tpl_file      = "core2/html/" . THEME . "/index2.tpl";
                $tpl_file_menu = "core2/html/" . THEME . "/menu.tpl";
            }


            $tpl      = new Templater3($tpl_file);
            $tpl_menu = new Templater3($tpl_file_menu);

            $tpl->assign('{system_name}', $this->getSystemName());

            $favicon = $this->getSystemFavicon();

            $tpl->assign('favicon.png', isset($favicon['png']) && is_file($favicon['png']) ? $favicon['png'] : '');
            $tpl->assign('favicon.ico', isset($favicon['ico']) && is_file($favicon['ico']) ? $favicon['ico'] : '');


            $tpl_menu->assign('<!--SYSTEM_NAME-->',        $this->getSystemName());
            $tpl_menu->assign('<!--CURRENT_USER_LOGIN-->', htmlspecialchars($this->auth->NAME));
            $tpl_menu->assign('<!--CURRENT_USER_FN-->',    htmlspecialchars($this->auth->FN));
            $tpl_menu->assign('<!--CURRENT_USER_LN-->',    htmlspecialchars($this->auth->LN));
            $tpl_menu->assign('[GRAVATAR_URL]',            "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))));


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

                $location      = $this->getModuleLocation($module_id); //получение расположения модуля
                $modController = "Mod" . ucfirst($module_id) . "Controller";
                $file_path     = $location . "/" . $modController . ".php";

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
                            $module_js = $modController->topJs()
                        ) {
                            $modules_js = array_merge($modules_js, $module_js);
                        }

                        if ($modController instanceof TopCss &&
                            $module_css = $modController->topCss()
                        ) {
                            $modules_css = array_merge($modules_css, $module_css);
                        }

                        if (THEME !== 'default' &&
                            ! Tool::isMobileBrowser() &&
                            $modController instanceof Navigation &&
                            $module_navigation_items = $modController->getNavigationItems()
                        ) {
                            $navigate_items[$module_id] = $module_navigation_items;
                        }
                    }
                    ob_clean();
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
                            $tpl_menu->navigate_item->assign('[MODULE_NAME]', $module_name);
                            $tpl_menu->navigate_item->assign('[HTML]',        $this->renderNavigateItem($item));
                            $tpl_menu->navigate_item->reassign();
                        }
                    }
                }
            }


            $tpl->assign('<!--index-->', $tpl_menu->render());
            $out = '';

            if ( ! empty($modules_css)) {
                foreach ($modules_css as $src) {
                    $out .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
                }
            }

            if ( ! empty($modules_js)) {
                foreach ($modules_js as $src) {
                    $out .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
                }
            }

            $tpl->assign('<!--xajax-->', "<script type=\"text/javascript\">var coreTheme  ='" . THEME . "'</script>" . $xajax->getJavascript() . $out);





            if (isset($this->config->system->js)) {
                $system_js = "";

                if (is_object($this->config->system->js)) {
                    foreach ($this->config->system->js as $src) {
                        if (file_exists($src)) {
                            $system_js .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
                        }
                    }

                } elseif (file_exists($src)) {
                    $system_js .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
                }

                $tpl->assign("<!--system_js-->", $system_js);
            }


            if (isset($this->config->system->css)) {
                $system_css = "";

                if (is_object($this->config->system->css)) {
                    foreach ($this->config->system->css as $src) {
                        if (file_exists($src)) {
                            $system_css .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
                        }
                    }

                } elseif (file_exists($src)) {
                    $system_css .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
                }

                $tpl->assign("<!--system_css-->", $system_css);
            }

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
                case 'link':
                    $link = ! empty($navigate_item['link'])
                        ? $navigate_item['link']
                        : '#';
                    $on_click = ! empty($navigate_item['onclick'])
                        ? $navigate_item['onclick']
                        : "if (event.button === 0 && ! event.ctrlKey) load('{$link}');";

                    $tpl = new Templater3(DOC_ROOT . '/core2/html/' . THEME . '/html/navigation.link.html');
                    $tpl->assign('[TITLE]',   ! empty($navigate_item['title']) ? $navigate_item['title'] : '');
                    $tpl->assign('[ICON]',    ! empty($navigate_item['icon']) ? $navigate_item['icon'] : '');
                    $tpl->assign('[CLASS]',   ! empty($navigate_item['class']) ? $navigate_item['class'] : '');
                    $tpl->assign('[ID]',      ! empty($navigate_item['id']) ? $navigate_item['id'] : '');
                    $tpl->assign('[LINK]',    $link);
                    $tpl->assign('[ONCLICK]', $on_click);
                    $html = $tpl->render();
                    break;

                case 'list':
                    $tpl = new Templater3(DOC_ROOT . '/core2/html/' . THEME . '/html/navigation.list.html');
                    $tpl->assign('[TITLE]', ! empty($navigate_item['title']) ? $navigate_item['title'] : '');
                    $tpl->assign('[ICON]',  ! empty($navigate_item['icon']) ? $navigate_item['icon'] : '');

                    if ( ! empty($navigate_item['list'])) {
                        foreach ($navigate_item['list'] as $list_item) {

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

	                $mod_instance = new $mod_cli();
	                $result = call_user_func_array(array($mod_instance, $action), $params);

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
                'version' => '',
                'params'  => array(),
                'query'   => $_SERVER['QUERY_STRING']
            );

            $co = count($temp2);
            if ($co) {
                if ($co > 1) {
                    $i = 0;
                    foreach ($temp2 as $k => $v) {
                        if ($i == 0) {
                            $route['module'] = strtolower($v);
                            $_GET['module'] = $route['module']; //DEPRECATED
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
                    unset($modsList[$k]);
                }
            }
            $data = [
                'system_name' => strip_tags($this->getSystemName()),
                'id'          => $this->auth->ID,
                'name'        => $this->auth->LN . ' ' . $this->auth->FN . ' ' . $this->auth->MN,
                'login'       => $this->auth->NAME,
                'avatar'      => "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))),
                'modules'     => $modsList
            ];

            return json_encode([
                'status' => 'success',
                'data'   => $data,
            ]);
        }


        /**
         * Установка контекста выполнения скрипта
         * @param string $module
         * @param string $action
         */
        private function setContext($module, $action = 'index') {
            Zend_Registry::set('context', array($module, $action));
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

        $acl = new \Core2\Acl();

        Zend_Registry::set('context', array($params['module'], !empty($params['action']) ? $params['action'] : 'index'));

        if ($params['module'] == 'admin') {
            require_once DOC_ROOT . 'core2/mod/admin/ModAjax.php';
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

            $db        = new \Core2\Db;
            $location  = $db->getModuleLocation($params['module']);
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