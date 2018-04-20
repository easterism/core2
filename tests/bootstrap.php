<?php

header('Content-Type: text/html; charset=utf-8');

// Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
define("DOC_ROOT", realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR);
define("DOC_PATH", substr(realpath(__DIR__ . '/../..'), strlen($_SERVER['DOCUMENT_ROOT'])) ?: '/');

$autoload_file = DOC_ROOT . "core2/vendor/autoload.php";
if ( ! file_exists($autoload_file)) {
    \Core2\Error::Exception("Composer autoload is missing.");
}


require_once $autoload_file;
require_once DOC_ROOT . 'core2/inc/classes/Error.php';

use Zend\Cache\StorageFactory;
use Zend\Session\Config\SessionConfig;
use Zend\Session\SessionManager;
use Zend\Session\SaveHandler\Cache;
use Zend\Session\Container as SessionContainer;


$config = [
    'system'       => ['name' => 'CORE'],
    'include_path' => DOC_ROOT,
    'cache'        => (isset($GLOBALS['CACHE']) ? $GLOBALS['CACHE'] : DOC_ROOT . 'core2/tests/cache'),
    'temp'         => getenv('TMP'),
    'debug'        => ['on' => false],
    'session'      => [
        'cookie_httponly'  => true,
        'use_only_cookies' => true
    ],
    'mail' => [
        'server' => (isset($GLOBALS['MAIL_SERVER']) ? $GLOBALS['MAIL_SERVER'] : ''),
        'port'   => (isset($GLOBALS['MAIL_PORT']) ? $GLOBALS['MAIL_PORT'] : '')
    ],
    'database' => [
        'adapter' => 'Pdo_Mysql',
        'params'  => [
            'charset'          => 'utf8',
            'adapterNamespace' => 'Core_Db_Adapter',
            'dbname'           => (isset($GLOBALS['DB_NAME']) ? $GLOBALS['DB_NAME'] : ''),
            'username'         => (isset($GLOBALS['DB_USER']) ? $GLOBALS['DB_USER'] : ''),
            'password'         => (isset($GLOBALS['DB_PASSWD']) ? $GLOBALS['DB_PASSWD'] : ''),
            'host'             => (isset($GLOBALS['DB_HOST']) ? $GLOBALS['DB_HOST'] : '')
        ],
        'isDefaultTableAdapter' => true,
        'profiler'              => [
            'enabled' => false,
            'class'   => 'Zend_Db_Profiler_Firebug'
        ],
        'caseFolding'                => true,
        'autoQuoteIdentifiers'       => true,
        'allowSerialization'         => true,
        'autoReconnectOnUnserialize' => true
    ]
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
require_once DOC_ROOT . 'core2/inc/classes/' . $config->database->params->adapterNamespace . "_{$config->database->adapter}.php";

//конфиг стал только для чтения
$config->setReadOnly();


if (isset($config->include_path) && $config->include_path) {
    set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
}

//подключаем мультиязычность
require_once DOC_ROOT . 'core2/inc/classes/I18n.php';
$translate = new \Core2\I18n($config);

if (isset($config->auth) && $config->auth->on) {
    $realm = $config->auth->params->realm;
    $users = $config->auth->params->users;
    if ($code = Tool::httpAuth($realm, $users)) {
        if ($code == 1) \Core2\Error::Exception("Неверный пользователь.");
        if ($code == 2) \Core2\Error::Exception("Неверный пароль.");
    }
}

require_once DOC_ROOT . 'core2/inc/classes/Log.php';

//устанавливаем шкурку
if ( ! empty($config->theme)) {
    define('THEME', $config->theme);
} else {
    define('THEME', 'default');
}

// DEPRECATED!!! MPDF PATH
define("_MPDF_TEMP_PATH",      rtrim($config->cache, "/") . '/');
define("_MPDF_TTFONTDATAPATH", rtrim($config->cache, "/") . '/');

//сохраняем параметры сессии
if ($config->session) {
    $sess_config = new SessionConfig();
    $sess_config->setOptions($config->session);
    $sess_manager = new SessionManager($sess_config);
    if ($config->session->phpSaveHandler && $config->session->phpSaveHandler == 'memcache') {
        $cache = StorageFactory::factory(array(
            'adapter' => array(
                'name' => 'memcached',
                'options' => array(
                    'servers' => array("host" => $config->session->savePath)
                ),
            )
        ));
        $sess_manager->setSaveHandler(new Cache($cache));
    }
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


require_once DOC_ROOT . 'core2/inc/classes/Zend_Session_Namespace.php'; //DEPRECATED
require_once DOC_ROOT . 'core2/inc/classes/Db.php';
require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater.php'; //DEPRECATED
require_once DOC_ROOT . 'core2/inc/classes/Templater2.php';