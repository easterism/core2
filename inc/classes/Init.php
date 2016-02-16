<?
    header('Content-Type: text/html; charset=utf-8');

    // Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
    define("DOC_ROOT", dirname($_SERVER['SCRIPT_FILENAME']) . "/");
    define("DOC_PATH", substr(DOC_ROOT, strlen($_SERVER['DOCUMENT_ROOT'])) ? : '/');

    $conf_file = DOC_ROOT . "core2/vendor/autoload.php";
    if (!file_exists($conf_file)) {
        Error::Exception("Отсутствует загрузчик.");
    }
    require_once($conf_file);
    require_once("Error.php");
    require_once("Zend/Config.php");
    require_once("Zend/Config/Ini.php");

    $conf_file = DOC_ROOT . "conf.ini";

    if (!file_exists($conf_file)) {
        Error::Exception("Отсутствует конфигурационный файл.");
    }
    $config = array(
        'system'       => array('name' => 'CORE'),
        'include_path' => '',
        'cache'        => 'cache',
        'temp'         => getenv('TMP'),
        'debug'        => array('on' => false),
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
        Error::Exception($e->getMessage());
    }

    // отладка приложения
    if ($config->debug->on) {
        ini_set('display_errors', 1);
    } else {
        ini_set('display_errors', 0);
    }

    // определяем путь к папке кеша
    if (strpos($config->cache, '/') === false) {
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
			if ($code == 1) Error::Exception("Неверный пользователь.");
			if ($code == 2) Error::Exception("Неверный пароль.");
		}
	}

	require_once("Log.php");
	require_once("Zend/Db.php");
	require_once("Zend/Session.php");
	require_once("Zend/Cache.php");
	require_once("Zend/Json.php"); //DEPRECATED

	//устанавливаем шкурку
	if (!empty($config->theme)) {
		define('THEME', $config->theme);
	} else {
		define('THEME', 'default');
	}
	//MPDF PATH
	define("_MPDF_TEMP_PATH", rtrim($config->cache, "/") . '/');
	define("_MPDF_TTFONTDATAPATH", rtrim($config->cache, "/") . '/');

	//сохраняем параметры сессии
	if ($config->session) {
		Zend_Session::setOptions($config->session->toArray());
	}

	//сохраняем конфиг
	Zend_Registry::set('config', $config);

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

		public function __construct() {
			parent::__construct();

			if (empty($_SERVER['HTTPS'])) {
				$tz = $this->config->system->https;
				if (!empty($tz)) {
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
            if ($auth) {
                $this->auth = $auth;
                Zend_Registry::set('auth', $this->auth);
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
            if (!function_exists('apache_request_headers')) return; // потому что недоступна в CLI < 5.5.7
            // обработка запросов от клиентов с Web Token
            $headers = apache_request_headers();
            //echo "<pre>";print_r($headers);echo "</pre>";die;
            //$headers['Authorization'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIiwianRpIjoiNTYyZTk3MDE4ZjA3ZiJ9.eyJpc3MiOiJhZG1pbi5tYXNoYTI0LmJ5IiwiYXVkIjoiNTI0YjU4YTE1MmJmMWNjMiIsImp0aSI6IjU2MmU5NzAxOGYwN2YiLCJpYXQiOjE0NDU4OTM4ODksIm5iZiI6MTQ0NTg5Mzk0OSwiZXhwIjoxNDQ1OTgwMjg5fQ.';
            $token = '';
            if (!empty($headers['Authorization'])) {
                if (strpos('Bearer', $headers['Authorization']) !== 0) return;
                $token = $headers['Authorization'];
            }
            if (!empty($headers['core2m'])) {
                $token = $headers['core2m'];
            }
            if ($token) {
                Zend_Registry::set('auth', new StdClass()); //Необходимо для правильной работы контроллера

                if ( ! $this->isModuleActive('webservice')) {
                    Error::catchJsonException(array('message' => $this->translate->tr('Модуль Webservice не активен')), 503);
                }

                $webservice_controller_path = $this->getModuleLocation('webservice') . '/ModWebserviceController.php';

                if ( ! file_exists($webservice_controller_path)) {
                    Error::catchJsonException(array('message' => $this->translate->tr('Модуль Webservice не существует')), 500);
                }

                require_once($webservice_controller_path);

                if ( ! class_exists('ModWebserviceController')) {
                    Error::catchJsonException(array('message' => $this->translate->tr('Модуль Webservice сломан')), 500);
                }

                $webservice_controller = new ModWebserviceController();
                return $webservice_controller->dispatchWebToken($token);
            }
        }

	/**
	 * The main dispatcher
     *
	 * @return mixed|string
	 * @throws Exception
	 */
	public function dispatch() {

		if (PHP_SAPI === 'cli') {
			return $this->cli();
		}

        // Веб-сервис (SOAP)
        $matches = array();
        if (preg_match('~^(wsdl_([a-zA-Z0-9_]+)\.xml|ws_([a-zA-Z0-9_]+)\.php)~', basename($_SERVER['REQUEST_URI']), $matches)) {

            // Инициализация модуля вебсервиса
            if ( ! $this->isModuleActive('webservice')) {
                throw new Exception($this->translate->tr("Модуль Webservice не активен"));
            }

            $webservice_location        = $this->getModuleLocation('webservice');
            $webservice_controller_path = $webservice_location . '/ModWebserviceController.php';

            if ( ! file_exists($webservice_controller_path)) {
                throw new Exception($this->translate->tr("Модуль Webservice не существует"));
            }

            require_once($webservice_controller_path);

            if ( ! class_exists('ModWebserviceController')) {
                throw new Exception($this->translate->tr("Модуль Webservice сломан"));
            }

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
        if (preg_match('~api/([a-zA-Z0-9_]+)(?:/|)([^?]*?)(?:/|)(?:\?|$)~', $_SERVER['REQUEST_URI'], $matches)) {

            // Инициализация модуля вебсервиса
            if ( ! $this->isModuleActive('webservice')) {
                return Error::catchJsonException(array('message' => 'Module webservice does not active'), 503);
            }

            $webservice_location        = $this->getModuleLocation('webservice');
            $webservice_controller_path = $webservice_location . '/ModWebserviceController.php';

            if ( ! file_exists($webservice_controller_path)) {
                return Error::catchJsonException(array('message' => 'Module does not exists'), 500);
            }

            require_once($webservice_controller_path);

            if ( ! class_exists('ModWebserviceController')) {
                return Error::catchJsonException(array('message' => 'Module broken'), 500);
            }

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

            $webservice_controller = new ModWebserviceController();
            return $webservice_controller->dispatchRest(strtolower($matches[1]), $action);
        }

		if (!empty($this->auth->ID) && !empty($this->auth->NAME) && is_int($this->auth->ID)) {
			// LOG USER ACTIVITY
			$logExclude = array('module=profile&unread=1'); //TODO сделать управление исключениями
			$this->logActivity($logExclude);
			//TODO CHECK DIRECT REQUESTS except iframes

			// SETUP ACL
			require_once 'core2/inc/classes/Acl.php';
            require_once 'core2/inc/Interfaces/Delete.php';
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
				$action = "action_" . strtolower($_GET['action']);
				if (method_exists($core, $action)) {
					return $core->$action();
				} else {
					throw new Exception($this->translate->tr("Субмодуль не существует"));
				}

			} else {
				if (empty($_GET['action']) || $_GET['action'] == 'index') {
					if (!$this->acl->checkAcl($module, 'access')) {
						throw new Exception(911);
					}
					$_GET['action'] = "index";
					if (!$this->isModuleActive($module)) throw new Exception($this->translate->tr("Модуль не существует"), 404);
				} else {
					$_GET['action'] = strtolower($_GET['action']);
                    $submodule_id = $module . '_' . $_GET['action'];
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
                    $this->requireController($location, $modController);
					$modController = new $modController();
					$action = "action_" . $_GET['action'];
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
            require_once("core2/ext/xajax_0.5_minimal/xajax_core/xajax.inc.php");
            $xajax = new xajax();
            //$xajax->configure("debug", true);
            $xajax->configure('javascript URI', 'core2/ext/xajax_0.5_minimal/');
            $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
            //$xajax->registerFunction('post');
            $xajax->processRequest();

            $mods   = $this->getModuleList();
            $tpl    = new Templater2();
            if ($this->auth->MOBILE) {
                header('Content-type: application/json; charset="utf-8"');
                $modsList = array();
                foreach ($mods as $data) {
                    $modsList[$data['m_id']] = array('module_id' => $data['module_id'], 'm_name' => $data['m_name'], 'submodules' => array());
                }
                foreach ($mods as $data) {
                    if (!empty($data['sm_id'])) {
                        $modsList[$data['m_id']]['submodules'][] = array('sm_id' => $data['sm_id'], 'sm_key' => $data['sm_key'], 'sm_name' => $data['sm_name']);
                    }
                }
                $modsList = array('system_name' => $this->getSystemName(),
                                  'login' => $this->auth->NAME,
                                  'avatar' => "http://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))),
                                  'modules' => $modsList);
                return json_encode($modsList);
            } else if (Tool::isMobileBrowser()) {
                $tpl->loadTemplate("core2/html/" . THEME . "/indexMobile2.tpl");
                $tpl2 = new Templater2("core2/html/" . THEME . "/menuMobile.tpl");
            } else {
                $tpl->loadTemplate("core2/html/" . THEME . "/index2.tpl");
                $tpl2 = new Templater2("core2/html/" . THEME . "/menu.tpl");
            }
            $tpl->assign('{system_name}', $this->getSystemName());

            $tpl2->assign('<!--SYSTEM_NAME-->',        $this->getSystemName());
            $tpl2->assign('<!--CURRENT_USER_LOGIN-->', $this->auth->NAME);
            $tpl2->assign('[GRAVATAR_URL]',            "http://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth->EMAIL))));


            $modtpl = $tpl2->getBlock('modules');
            $html   = "";
            $js     = array();
            foreach ($mods as $data) {
                if (!empty($data['sm_key'])) continue;

                if ($data['isset_home_page'] == 'N') {
                    $first_action = 'index';
                    foreach ($mods as $mod) {
                        if ( ! empty($mod['sm_id']) && $data['m_id'] == $mod['m_id']) {
                            $first_action = $mod['sm_key'];
                            break;
                        }
                    }
                    $url           = "index.php?module={$data['module_id']}&action={$first_action}";
                    $module_action = "&action={$first_action}";
                } else {
                    $url           = "index.php?module=" . $data['module_id'];
                    $module_action = '';
                }

                $html .= str_replace(
                    array('[MODULE_ID]', '[MODULE_NAME]', '[MODULE_ACTION]', '[MODULE_URL]'),
                    array($data['module_id'], $data['m_name'], $module_action, $url),
                    $modtpl
                );
                if ($data['module_id'] == 'admin') continue;
                $location = $this->getModuleLocation($data['module_id']); //получение расположения модуля
                $modController = "Mod" . ucfirst(strtolower($data['module_id'])) . "Controller";
                $file_path = $location . "/" . $modController . ".php";
                if (file_exists($file_path)) {
                    ob_start();
                    require_once $file_path;
                    if (class_exists($modController)) { // подключаем класс модуля
                        $modController = new $modController();
                        if (method_exists($modController, 'topJs')) {
                            if ($modEvent = $modController->topJs()) {
                                $js += $modEvent;
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
                    if (!$submodule['sm_id']) continue;
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
                    throw new Exception("File controller '{$controller_path}' does not exists");
                }

                require_once $controller_path;

                if ( ! class_exists($mod_controller)) {
                    throw new Exception("Class controller '{$mod_controller}' not found");
                }

                $mod_methods = get_class_methods($mod_controller);
                $cli_method = 'cli' . ucfirst($action);
                if ( ! array_search($cli_method, $mod_methods)) {
                    throw new Exception("Cli method '$cli_method' not found in controller '{$mod_controller}'");
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
                    Error::catchXajax($e, $res);
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
                        Error::catchXajax($e, $res);
                    }
                } else {
                    throw new Exception($translate->tr("Метод не найден"), 60);
                }
            }
        }

        return $res;
    }