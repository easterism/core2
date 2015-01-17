<?

header('Content-Type: text/html; charset=utf-8');

	// Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
define("DOC_ROOT", dirname($_SERVER['SCRIPT_FILENAME']) . "/");
define("DOC_PATH", substr(dirname($_SERVER['SCRIPT_FILENAME']), strlen($_SERVER['DOCUMENT_ROOT'])) ?: '/');


require_once 'Tool.php';
require_once 'Error.php';

if (!Tool::file_exists_ip("/Zend/Config.php")) {
	Error::Exception("Требуется ZF компонент \"Config\"");
}

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
	$config['temp'] = getenv('TEMP');
	if (empty($config['temp'])) {
		$config['temp'] = "/tmp";
	}
}
try {
	$config = new Zend_Config($config, true);

	if (PHP_SAPI === 'cli' && ! empty($_SERVER['argv'][1])) {
		parse_str(implode('&', array_slice($_SERVER['argv'], 1)), $options);
		if ( ! empty($options['host'])) {
			$_SERVER['SERVER_NAME'] = $options['host'];
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
// определяем путь к папке кеша
	if (strpos($config->cache, '/') === false) {
		$config->cache = DOC_ROOT . trim($config->cache, "/");
	}
//подключаем собственный адаптер
require_once($config->database->params->adapterNamespace . "_{$config->database->adapter}.php");


//конфиг стал только для чтения
$config->setReadOnly();

if (isset($config->include_path) && $config->include_path) {
	set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
}

if (isset($config->auth) && $config->auth->on) {
	$realm = $config->auth->params->realm;
	$users = $config->auth->params->users;
	if ($code = Tool::httpAuth($realm, $users)) {
		if ($code == 1) Error::Exception("Неверный пользователь.");
		if ($code == 2) Error::Exception("Неверный пароль.");
	}
}

if (!Tool::file_exists_ip("/Zend/Registry.php")) {
	Error::Exception("Требуется ZF компонент \"Registry\"");
}
if (!Tool::file_exists_ip("/Zend/Db.php")) {
	Error::Exception("Требуется ZF компонент \"Db\"");
}
if (!Tool::file_exists_ip("/Zend/Session.php")) {
	Error::Exception("Требуется ZF компонент \"Session\"");
}
if (!Tool::file_exists_ip("/Zend/Acl.php")) {
	Error::Exception("Требуется ZF компонент \"Acl\"");
}

require_once("Zend/Registry.php");
require_once("Zend/Db.php");
require_once("Zend/Session.php");
require_once("Zend/Json.php");
require_once("Zend/Cache.php");

//устанавливаем шкурку
if (!empty($config->theme)) {
	define('THEME', $config->theme);
} else {
	define('THEME', 'default');
}
//MPDF PATH
define("_MPDF_TEMP_PATH", DOC_ROOT . trim($config->cache, "/") . '/');
define("_MPDF_TTFONTDATAPATH", DOC_ROOT . trim($config->cache, "/") . '/');

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
	
	public function checkAuth() {
		$this->auth 	= new Zend_Session_Namespace('Auth', true);
		if (!isset($this->auth->initialized)) { //регенерация сессии для предотвращения угона
			Zend_Session::regenerateId();
			$this->auth->initialized = true;
		}
		Zend_Registry::set('auth', $this->auth);
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
	 * The main dispatcher
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
                throw new Exception("Module webservice does not active");
            }

            $webservice_location        = $this->getModuleLocation('webservice');
            $webservice_controller_path = $webservice_location . '/ModWebserviceController.php';

            if ( ! file_exists($webservice_controller_path)) {
                throw new Exception("Module does not exists");
            }

            require_once($webservice_controller_path);

            if ( ! class_exists('ModWebserviceController')) {
                throw new Exception("Module broken");
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
                    $action = $matches[2];
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
			$this->logActivity($this->auth, $logExclude);
			//TODO CHECK DIRECT REQUESTS except iframes

			// SETUP ACL
			require_once('core2/inc/classes/Acl.php');
			$this->acl = new Acl();
			$this->acl->setupAcl();

		} else {
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
			if (empty($_GET['module'])) throw new Exception("Модуль не найден");
			$module = strtolower($_GET['module']);
			if ($module === 'admin') {
				require_once 'core2/inc/CoreController.php';
				$core = new CoreController();
				if (empty($_GET['action'])) {
					$_GET['action'] = 'index';
				}
				$action = "action_" . strtolower($_GET['action']);
				if (method_exists($core, $action)) {
					return $core->$action();
				} else {
					throw new Exception("No action");
				}

			} else {
				if (empty($_GET['action']) || $_GET['action'] == 'index') {
					if (!$this->acl->checkAcl($module, 'access')) {
						throw new Exception(911);
					}
					$_GET['action'] = "index";
					$mods = $this->db->fetchRow("SELECT m.m_id, m_name, m.module_id, is_system
											 FROM core_modules AS m
											WHERE m.visible = 'Y'
											  AND m.module_id = ?",
						$module);
				} else {
					$_GET['action'] = strtolower($_GET['action']);
					$mods = $this->db->fetchRow("SELECT m.m_id, m_name, sm_path, m.module_id, is_system, sm.m_id AS sm_id
											 FROM core_modules AS m
												  LEFT JOIN core_submodules AS sm ON sm.m_id = m.m_id AND sm.visible = 'Y'
											WHERE m.visible = 'Y'
											  AND m.module_id = ?
											  AND sm_key = ?
											  ORDER BY sm.seq",
						array($module, $_GET['action'])
					);
					if ($mods['sm_id'] && !$this->acl->checkAcl($module . '_' . $_GET['action'], 'access')) {
						throw new Exception(911);
					}
				}

                $location = $this->getModuleLocation($mods['module_id']);

				if (empty($mods['sm_path'])) {
					$modController = "Mod" . ucfirst(strtolower($mods['module_id'])) . "Controller";
                    $controller_path = $location . "/" . $modController . ".php";
					if (!file_exists($controller_path)) {
						throw new Exception("Module does not exists");
					}
					require_once $controller_path;
					if (!class_exists($modController)) {
						throw new Exception("Module broken");
					}
					$modController = new $modController();
					$action = "action_" . $_GET['action'];
					if (method_exists($modController, $action)) {
						return $modController->$action();
					} else {
						throw new Exception("No action");
					}
				} else {
					header("Location: " . $mods['sm_path']);
				}
			}
		}
	}
	
	private function getSystemName() {
		$res = $this->config->system->name;
		return $res;
	}
	
	private function getSystemLogo() {
		$res = $this->config->system->logo;
		if (!empty($res) && is_file($res)) {
			return $res;
		} else {
			return 'core2/html/'.THEME.'/img/logo.gif';
		}
	}
	
	/**
	 * Create login page
	 */
	protected function getLogin() {
		$tokenNamespace = new Zend_Session_Namespace('Token');
		if (isset($_POST['action']) && $tokenNamespace->TOKEN) {
			require_once 'core2/inc/CoreController.php';
			$core = new CoreController();
			$core->action_login();
		}
		$tpl = new Templater();
		if (Tool::isMobileBrowser()) {
			$tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.tpl");
		} else {
			$tpl->loadTemplate("core2/html/" . THEME . "/login/index.tpl");
		}

		$tpl->assign('{system_name}', $this->getSystemName());
		$tpl2 = new Templater();
		$tpl2->loadTemplate("core2/html/" . THEME . "/login/login.tpl");

		$errorNamespace = new Zend_Session_Namespace('Error');
		$blockNamespace = new Zend_Session_Namespace('Block');
		if (!empty($blockNamespace->blocked)) {
			$tpl2->touchBlock('error');
			$tpl2->assign('[ERROR_MSG]', $errorNamespace->ERROR);
			$tpl2->assign('[ERROR_LOGIN]', '');
		} elseif (!empty($errorNamespace->ERROR)) {
		    
			$tpl2->touchBlock('error');
			$tpl2->assign('[ERROR_MSG]', $errorNamespace->ERROR);
			$tpl2->assign('[ERROR_LOGIN]', $errorNamespace->TMPLOGIN);
			$errorNamespace->ERROR = '';
		} else {
			$tpl2->touchBlock('error');
			$tpl2->assign('[ERROR_MSG]', '');
			$tpl2->assign('[ERROR_LOGIN]', '');
		}
		$config = Zend_Registry::getInstance()->get('config');
		if (empty($config->ldap->active) || !$config->ldap->active) {
			$tpl2->assign('<form', "<form onsubmit=\"document.getElementById('gfhjkm').value=hex_md5(document.getElementById('gfhjkm').value)\"");
		}
		$logo = $this->getSystemLogo();
		if (is_file($logo)) {
			$tpl2->touchBlock('logo');
			$tpl2->assign('{logo}', $logo);
		}
		$u = crypt(uniqid());
		$tokenNamespace->TOKEN = $u;
		$tokenNamespace->setExpirationHops(1);
		$tokenNamespace->lock();
		$tpl2->assign('name="action"', 'name="action" value="' . $u . '"');
		$tpl->assign('<!--index -->', $tpl2->parse());
		return $tpl->parse();
	}

	/**
	 * Create the top menu
	 * @return mixed|string
	 */
	protected function getMenu() {
		require_once("core2/ext/xajax_0.5_minimal/xajax_core/xajax.inc.php");
		//require_once("core2/ext/xajax/xajax.inc.php");
		$xajax = new xajax();
		//$xajax->configure("debug", true);
		$xajax->configure('javascript URI', 'core2/ext/xajax_0.5_minimal/');
		$xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
		//$xajax->registerFunction('post');
		$xajax->processRequest();

		$tpl = new Templater();
		if (Tool::isMobileBrowser()) {
			$tpl->loadTemplate("core2/html/" . THEME . "/indexMobile2.tpl");
			$tpl2 = new Templater("core2/html/" . THEME . "/menuMobile.tpl");
		} else {
			$tpl->loadTemplate("core2/html/" . THEME . "/index2.tpl");
			$tpl2 = new Templater("core2/html/" . THEME . "/menu.tpl");
		}
		$tpl->assign('{system_name}', $this->getSystemName());

		$mods = $this->db->fetchAll("SELECT m_id, m_name, module_id, is_public FROM core_modules WHERE visible='Y' ORDER BY seq");
		if ($this->auth->ADMIN || $this->auth->NAME == 'root') {
			$mods[] = array('m_id' => -1, 'm_name' => 'Админ', 'module_id' => 'admin', 'is_public' => 'Y');
		} 
		$modtpl = $tpl2->getBlock('modules');
		$html = "";
		$js = array();
		foreach ($mods as $data) {
			if ($this->acl->checkAcl($data['module_id'], 'access')) {
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
				if ($data['is_public'] == 'Y') {
					$url = "index.php?module=" . $data['module_id'];
					$html .= str_replace(array('[MODULE_ID]', '[MODULE_NAME]', '[MODULE_URL]'),
										 array($data['module_id'], $data['m_name'], $url), $modtpl);
				}
			}
		}
		
		$mods = $this->db->fetchAll("SELECT sm_id, sm_name, sm_key, sm.m_id, module_id 
								 FROM core_submodules AS sm
								 	  INNER JOIN core_modules AS m ON m.m_id = sm.m_id AND m.visible='Y'
								WHERE sm.visible = 'Y'
								ORDER BY sm.seq
							  ");
		if ($this->auth->ADMIN || $this->auth->NAME === 'root') {
			$mods[] = array('sm_id' => -1, 'sm_name' => 'Модули', 			'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'modules', 'loc' => 'core'); 
			$mods[] = array('sm_id' => -2, 'sm_name' => 'Конфигурация', 	'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'settings', 'loc' => 'core'); 
			$mods[] = array('sm_id' => -3, 'sm_name' => 'Справочники',		'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'enum', 'loc' => 'core'); 
			$mods[] = array('sm_id' => -4, 'sm_name' => 'Пользователи', 	'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'users', 'loc' => 'core'); 
			$mods[] = array('sm_id' => -5, 'sm_name' => 'Роли', 			'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'roles', 'loc' => 'core');
			$mods[] = array('sm_id' => -6, 'sm_name' => 'Мониторинг', 		'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'monitoring', 'loc' => 'core');
			$mods[] = array('sm_id' => -7, 'sm_name' => 'Аудит', 			'm_id' => '-1', 'module_id' => 'admin', 'sm_key' => 'audit', 'loc' => 'core');
		} 
		$modtpl = $tpl2->getBlock('submodules');
		$html2 = "";
		foreach ($mods as $data) {
			if ($this->acl->checkAcl($data['module_id'] . '_' . $data['sm_key'], 'access')) {
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
		$tpl->assign('<!--xajax-->', $xajax->getJavascript() . $out);
		$html = str_replace("<!--modules-->", $html, $tpl->parse());
		$html = str_replace("<!--submodules-->", $html2, $html);
		return $html;
	}
	
	protected function get404() {
		throw new Exception(404);
	}




	/**
	 * Cli
	 * @return null
	 * @throws Exception
	 */
	private function cli() {

		$options = array();

		if ( ! empty($_SERVER['argv'][1])) {
			parse_str(implode('&', array_slice($_SERVER['argv'], 1)), $options);
		}

		if ((empty($options) && $_SERVER['argc'] == 1) || ! isset($_SERVER['argv'][1]) || isset($options['help'])) {
			return implode(PHP_EOL, array(
				'Core 2',
				'Usage: php -f index.php [OPTIONS]',
				'Optional arguments:',
				"\tcron\tCron action",
				"\tjob\tCron job id. Optional.\n",
				"\thost\tHost in config file.\n",
				"\thelp\tHelp message\n",
				"Example of usage:",
				"php -f index.php cron=run job=123\n",
			));
		}

		if (isset($options['cron'])) {
			$cron_action = $options['cron'];
			$job_id 	 = isset($options['job']) ? (int)$options['job'] : 0;

			$this->db; // FIXME хак

			if ( ! $this->isModuleActive('cron')) {
				throw new Exception("Module does not active");
			}

			if ((int)substr($this->getModuleVersion('cron'), 0, 1) < 2) {
				throw new Exception("Need cron version >= 2");
			}

			$mod_path  	     = $this->getModuleLocation("cron");
			$controller_path = $mod_path . '/ModCronController.php';

			if ( ! file_exists($controller_path)) {
				throw new Exception("Module does not exists");
			}

			require_once $mod_path . '/background/classes/Cron_Background_Job.php';
			require_once $controller_path;

			if ( ! class_exists("ModCronController")) {
				throw new Exception("Module broken");
			}


			switch ($cron_action) {
				case 'run' :
					if ($job_id === 0) {
						$cron_controller = new ModCronController();
						$cron_controller->run();
					} else {
						$background_job = new Cron_Background_Job($job_id);
						$background_job->dispatch();
					}
					break;
				default : throw new Exception("Undefined cron action!"); break;
			}
		}

		return null;
	}
}

function post($func, $loc, $data) {
    $res = new xajaxResponse();
	$loc = explode('?', $loc);
	if (isset($loc[1])) {
		unset($loc[0]);
		$loc = implode('?', $loc);
	} else {
		$loc = $loc[0];
	}
	parse_str($loc, $params);
	if (empty($params['module'])) throw new Exception("No module", 60);
	$acl = new Acl();
	if ($params['module'] == 'admin') {
		require_once('core2/mod/ModAjax.php');
		$auth 	= Zend_Registry::get('auth');
		if (!$auth->ADMIN) throw new Exception(911);
		$xajax = new ModAjax($res);
		if (method_exists($xajax, $func)) {
			$xajax->setupAcl();
			try {
				return $xajax->$func($data);
			} catch (Exception $e) {
				Error::catchXajax($e, $res);
			}
		} else {
			throw new Exception("No action", 60);
		}
	} else {
		if (empty($params['action'])) {
			if (!$acl->checkAcl($params['module'], 'access')) {
				throw new Exception(911);
			}
			$params['action'] = 'index';
		} else {
			if (!$acl->checkAcl($params['module'] . '_' . $params['action'], 'access')) {
				throw new Exception(911);
			}
		}
        $db = new Db;
		$location = $db->getModuleLocation($params['module']);
        $file_path = $location . "/ModAjax.php";
		if (file_exists($file_path)) {
			require_once($file_path);
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
				throw new Exception("No method", 60);
			}
		}
	}
    return $res;
}