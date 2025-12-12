#!/usr/bin/env php
<?php
namespace Core2;

require_once 'inc/classes/Error.php';
require_once 'inc/classes/Cli.php';
require_once 'inc/classes/Registry.php';
require_once 'inc/classes/Config.php';
require_once 'inc/classes/I18n.php';

try {
    if (PHP_SAPI !== 'cli') {
        throw new \Exception("Allowed for CLI only.");
    }
    $autoload = __DIR__ . "/vendor/autoload.php";
    if (!file_exists($autoload)) {
        throw new \Exception("Composer autoload is missing.");
    }
    require_once($autoload);

    $_SERVER['SERVER_NAME'] = '_';
    $options = getopt('c:m:a:p:s:h', array(
        'config:',
        'module:',
        'action:',
        'param:',
        'section:',
        'help',
    ));

    if (empty($options) || isset($options['h']) || isset($options['help'])) {
        echo implode(PHP_EOL, array(
            'Core2',
            'Usage: php cli.php [OPTIONS]',
            'Optional arguments:',
            "   -c    --config    Path to application conf.ini (parent dir by default)",
            "   -m    --module    Module name",
            "   -a    --action    Cli method name",
            "   -p    --param     Parameter in method",
            "   -s    --section   Section name in conf.ini",
            "   -h    --help      Help info",
            "Examples of usage:",
            "php cli.php --module cron --action run",
            "php cli.php --module cron --action run --section site.com",
            "php cli.php --module cron --action runJob --param 123\n",
        ));
        return;
    }

    $conf_ini = __DIR__ . "/../conf.ini";
    if (( ! empty($options['config']) && is_string($options['config'])) || ( ! empty($options['c']) && is_string($options['c']))) {
        $conf_ini = ! empty($options['config']) ? $options['config'] : $options['c'];
    }
    $conf_ini = realpath($conf_ini);
    if (!$conf_ini || !file_exists($conf_ini)) {
        throw new \Exception("Missing config file.");
    }
    define("DOC_ROOT", dirname($conf_ini) . "/");
    if (( ! empty($options['section']) && is_string($options['section'])) || ( ! empty($options['s']) && is_string($options['s']))) {
        $_SERVER['SERVER_NAME'] = ! empty($options['section']) ? $options['section'] : $options['s'];
    }

    $section = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';

    $config = [
        'system'       => ['name' => 'CORE2'],
        'include_path' => '',
        'temp'         => getenv('TMP'),
        'debug'        => ['on' => true],
        'session'      => [
            'cookie_httponly'  => true,
            'use_only_cookies' => true,
        ],
        'database' => [
            'adapter' => 'Pdo_Mysql',
            'params'  => [
                'charset' => 'utf8',
                'driver_options'=> [
                    \PDO::ATTR_TIMEOUT => 5,
//                PDO::ATTR_PERSISTENT => true,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
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

    $conf     = new Config($config);
    $config   = $conf->getData()->merge($conf->readIni($conf_ini, $section));


    $conf_d = DOC_ROOT . "conf.ext.ini";
    if (file_exists($conf_d)) {
        $config->merge($conf->readIni($conf_d, $section));
    }

    $tz = $config->system->timezone;
    if (!empty($tz)) {
        date_default_timezone_set($tz);
    }


//проверяем настройки для базы данных
    if ($config->database) {
        if ($config->database->adapter === 'Pdo_Mysql') {
            $config->database->params->adapterNamespace = 'Core_Db_Adapter';
            //подключаем собственный адаптер базы данных
            require_once('inc/classes/' . $config->database->params->adapterNamespace . "_{$config->database->adapter}.php");
        } elseif ($config->database->adapter === 'Pdo_Pgsql') {
            $config->database->params->adapterNamespace = 'Zend_Db_Adapter';
            $config->database->schema = $config->database->params->dbname;
            $config->database->params->dbname = $config->database->pgname ? $config->database->pgname : 'postgres';
        }
        if (empty($config->database->params->dbname)) {
            throw new \Exception('No database found!');
        }
    }


//конфиг стал только для чтения
    $config->setReadOnly();


    if (isset($config->include_path) && $config->include_path) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $config->include_path);
    }

//сохраняем конфиг

    Registry::set('config', $config);

    //подключаем мультиязычность
    $translate = new I18n($config);

//обрабатываем конфиг ядра
    $core_conf_file = __DIR__ . "/conf.ini";
    if (file_exists($core_conf_file)) {
        $config = new Config();
        Registry::set('core_config', $config->readIni($core_conf_file, 'production'));
    }

    $module = isset($options['module']) ? $options['module'] : $options['m'];
    $action = isset($options['action']) ? $options['action'] : $options['a'];
    $params = isset($options['param'])
        ? $options['param']
        : (isset($options['p']) ? $options['p'] : false);

    $cli = new Cli();
    echo $cli->run($module, $action, $params);

} catch (\Exception $e) {
    Error::Exception($e);
}