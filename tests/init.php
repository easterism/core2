<?php

// Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
define("DOC_ROOT", realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR);
define("DOC_PATH", substr(realpath(__DIR__ . '/../..'), strlen($_SERVER['DOCUMENT_ROOT'])) ?: '/');

require_once DOC_ROOT . 'core2/inc/classes/Tool.php';
require_once DOC_ROOT . 'core2/inc/classes/Error.php';
require_once DOC_ROOT . 'core2/inc/classes/Db.php';
require_once DOC_ROOT . 'core2/inc/classes/Acl.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater2.php';



if ( ! Tool::file_exists_ip("/Zend/Config.php")) {
    \Core2\Error::Exception("Требуется ZF компонент \"Config\"");
}

require_once("Zend/Config.php");
require_once("Zend/Config/Ini.php");
require_once("Zend/Registry.php");
require_once("Zend/Cache.php");
require_once("Zend/Db.php");
require_once("Zend/Session.php");
require_once("Zend/Debug.php");
require_once("Zend/Log.php");
require_once("Zend/Json.php");
require_once("Zend/Translate.php");

$config = array(
    'system'       => array(
        'name'     => 'CORE',
        'timezone' => 'Europe/Minsk'
    ),
    'include_path' => DOC_ROOT,
    'cache'        => DOC_ROOT . 'core2/tests/cache',
    'temp'         => getenv('TMP'),
    'debug'        => array('on' => false),
    'log'          => array(
        'path' => __DIR__ . '/cache/xxx.log'
    ),
    'database'    => array(
        'adapter' => 'Pdo_Mysql',
        'params'  => array(
            'charset'          => 'utf8',
            'adapterNamespace' => 'Core_Db_Adapter',
            'dbname'   => (isset($GLOBALS['DB_NAME']) ? $GLOBALS['DB_NAME'] : ''),
            'username' => (isset($GLOBALS['DB_USER']) ? $GLOBALS['DB_USER'] : ''),
            'password' => (isset($GLOBALS['DB_PASSWD']) ? $GLOBALS['DB_PASSWD'] : ''),
            'host'     => (isset($GLOBALS['DB_HOST']) ? $GLOBALS['DB_HOST'] : '')
        ),
        'isDefaultTableAdapter' => true,
        'profiler'              => array(
            'enabled' => false,
            'class'   => 'Zend_Db_Profiler_Firebug'
        ),
        'caseFolding'                => true,
        'autoQuoteIdentifiers'       => true,
        'allowSerialization'         => true,
        'autoReconnectOnUnserialize' => true
    ),
    'php_dir' => (isset($GLOBALS['PHP_DIR']) ? $GLOBALS['PHP_DIR'] : '')
);


date_default_timezone_set($config['system']['timezone']);

if (empty($config['temp'])) {
    $config['temp'] = getenv('TEMP');
    if (empty($config['temp'])) {
        $config['temp'] = "/tmp";
    }
}
try {
    $config = new Zend_Config($config, true);
} catch (Zend_Config_Exception $e) {
    \Core2\Error::Exception($e->getMessage());
}

//подключаем собственный адаптер
require_once(DOC_ROOT . 'core2/inc/classes/' . $config->database->params->adapterNamespace . "_{$config->database->adapter}.php");

if (isset($config->include_path) && $config->include_path) {
    set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
}

if (isset($config->auth) && $config->auth->on) {
    $realm = $config->auth->params->realm;
    $users = $config->auth->params->users;
    if ($code = Tool::httpAuth($realm, $users)) {
        if ($code == 1) \Core2\Error::Exception("Неверный пользователь.");
        if ($code == 2) \Core2\Error::Exception("Неверный пароль.");
    }
}

if ( ! Tool::file_exists_ip("/Zend/Registry.php")) {
    \Core2\Error::Exception("Требуется ZF компонент \"Registry\"");
}
if ( ! Tool::file_exists_ip("/Zend/Db.php")) {
    \Core2\Error::Exception("Требуется ZF компонент \"Db\"");
}
if ( ! Tool::file_exists_ip("/Zend/Session.php")) {
    \Core2\Error::Exception("Требуется ZF компонент \"Session\"");
}
if ( ! Tool::file_exists_ip("/Zend/Acl.php")) {
    \Core2\Error::Exception("Требуется ZF компонент \"Acl\"");
}

require_once("Zend/Registry.php");
require_once("Zend/Db.php");
require_once("Zend/Session.php");
require_once("Zend/Json.php");
require_once("Zend/Cache.php");

//устанавливаем шкурку
if ( ! empty($config->theme)) {
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

require_once DOC_ROOT . 'core2/inc/classes/Db.php';
require_once DOC_ROOT . 'core2/inc/classes/Common.php';


$auth 	= new Zend_Session_Namespace('Auth', true);
if ( ! isset($auth->initialized)) { //регенерация сессии для предотвращения угона
    Zend_Session::regenerateId();
    $auth->initialized = true;
}
Zend_Registry::set('auth', $auth);


$acl = new Acl();
$acl->setupAcl();

Zend_Registry::set('acl', $acl);