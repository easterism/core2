<?php
use Core2\I18n;
use Core2\Registry;
use Core2\Error;


// Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
define("DOC_ROOT", realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define("DOC_PATH", substr(DOC_ROOT, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))) ?: '/');

$autoload = __DIR__ . "/vendor/autoload.php";

if ( ! file_exists($autoload)) {
    \Core2\Error::Exception("Composer autoload is missing.");
}

require_once $autoload;
require_once "inc/classes/Error.php";

if ( ! empty($_SERVER['REQUEST_URI'])) {
    $request_ext = explode(".", basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if ( ! empty($request_ext[1]) && in_array($request_ext[1], ['txt', 'js', 'css', 'env'])) {
        \Core2\Error::Exception("File not found", 404);
        return;
    }
}

require_once "inc/classes/Log.php";
require_once "inc/classes/Theme.php";
require_once "inc/classes/Registry.php";
require_once "inc/classes/Config.php";
require_once "inc/classes/Router.php";
require_once 'inc/classes/I18n.php';
require_once 'inc/classes/Common.php';
require_once 'inc/classes/Acl.php';
require_once 'inc/classes/SSE.php';

$conf_file = DOC_ROOT . "conf.ini";

if ( ! file_exists($conf_file)) {
    Error::Exception("conf.ini is missing.", 404);
}

// Memcached для кеширования конфигурации
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);
$memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000);
$memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

$cacheKey = 'core2_config_' . md5($_SERVER['SERVER_NAME'] ?? 'production');
$system_config = $memcached->get($cacheKey);
$configLoaded = false;
//обрабатываем общий конфиг
if ($system_config === false || !empty($_GET['reset_cache'])) {
    $config_origin = [
        'system'       => ['name' => 'CORE2'],
        'include_path' => '',
        'temp'         => getenv('TMP'),
        'debug'        => ['on' => false],
        'session'      => [
            'cookie_httponly'  => true,
            'use_only_cookies' => true,
        ],
        'database'     => [
            'adapter'               => 'Pdo_Mysql',
            'params'                => [
                'charset'        => 'utf8',
                'driver_options' => [
                    \PDO::ATTR_TIMEOUT => 5,
                    //                \PDO::ATTR_PERSISTENT => true,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ],
                'options'        => [
                    'caseFolding'                => false,
                    'autoQuoteIdentifiers'       => true,
                    'allowSerialization'         => true,
                    'autoReconnectOnUnserialize' => true,
                ],
            ],
            'isDefaultTableAdapter' => true,
            'profiler'              => [
                'enabled' => false,
                'class'   => 'Zend_Db_Profiler_Firebug',
            ],
        ],
    ];

    // определяем путь к темповой папке
    if (empty($config_origin['temp'])) {
        $config_origin['temp'] = sys_get_temp_dir();
    }

    //обрабатываем общий конфиг
    try {
        $section = ! empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';

        $conf          = new Core2\Config($config_origin);
        $system_config = $conf->getData()->merge($conf->readIni($conf_file, $section));


        $conf_d = __DIR__ . "/conf.ext.ini";
        if (file_exists($conf_d)) {
            $system_config->merge($conf->readIni($conf_d, $section));
        }

        $core_conf_file = __DIR__ . "/conf.ini";
        if (file_exists($core_conf_file)) {
            $core_config = new Core2\Config();
            $system_config->core2 = $core_config->readIni($core_conf_file, 'production');
        }


    } catch (Exception $e) {
        Error::Exception($e->getMessage());
    }
    $memcached->set($cacheKey, $system_config, 900);
    $configLoaded = true;
} else {
    $configLoaded = true;
}

if ( ! $configLoaded) {
    throw new Exception("Unable to load configuration.");
}
$tz = $system_config->system->timezone;
if ( ! empty($tz)) {
    date_default_timezone_set($tz);
}

// отладка приложения
if ($system_config->debug->on) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}
if (empty($_SERVER['HTTPS'])) {
    if (isset($system_config->system) && ! empty($system_config->system->https)) {
        header('Location: https://' . $_SERVER['SERVER_NAME']);
        return;
    }
}
//проверяем настройки для базы данных
if ($system_config->database) {
    if (empty($system_config->database->adapter)) {
        Error::Exception('Database adapter is empty!');
    }
    if (empty($system_config->database->params->dbname)) {
        Error::Exception('Database name is empty!');
    }
}

//конфиг стал только для чтения
$system_config->setReadOnly();


if (isset($system_config->include_path) && $system_config->include_path) {
    set_include_path(get_include_path() . PATH_SEPARATOR . $system_config->include_path);
}

//подключаем мультиязычность
$translate = new I18n($system_config);

//сохраняем конфиг
Registry::set('config', $system_config);
Registry::set('core_config', $system_config->core2);
