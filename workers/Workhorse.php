<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Zend_Registry.php';
require_once __DIR__ . '/../inc/classes/Common.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/I18n.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

class Workhorse
{

    public function __construct()
    {
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

        try {
            $config = new \Zend_Config($config, true);


            $section = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'production';
            $config2 = new Zend_Config_Ini($conf_file, $section);
            $conf_d = DOC_ROOT . "conf.ext.ini";
            if (file_exists($conf_d)) {
                $config2->merge(new Zend_Config_Ini($conf_d, $section));
            }
            $config->merge($config2);
        }
        catch (\Zend_Config_Exception $e) {
            Error::Exception($e->getMessage());
        }

        parent::__construct($config);
        $translate = new I18n($config);
    }

    public function run($job, &$log) {

        $id = $job->unique();

        $workload = json_decode($job->workload());
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
            return;
        }
        $_SERVER = $workload->server; //
        //$workload_size = $job->workloadSize();
        if (!empty($workload->module) && !empty($workload->location) && !empty($workload->worker)) {
            $config = unserialize($workload->config);
            \Zend_Registry::set('config',      $config);
            \Zend_Registry::set('context',     $workload->context);
            \Zend_Registry::set('auth',        $workload->auth);
            \Zend_Registry::set('core_config', new \Zend_Config_Ini(__DIR__ . "/../conf.ini", 'production'));

            $db = new \Core2\Db($config);
            $in_job = $db->db->fetchRow("SELECT * FROM core_worker_jobs WHERE id=?", $id);
            if ($in_job) {
                //задача уже обрабатывается
                return;
            }



            $controller = $this->requireController($workload->module, $workload->location);

            $handler = $job->handle();
            $db->db->insert("core_worker_jobs", [
                'id' =>    $id,
                'handler' =>    $handler,
                'status' =>    'start',
            ]);
            $db->db->closeConnection();

            define("DOC_ROOT", $workload->doc_root);
            $modWorker = new $controller();
            $action = $workload->worker;

            if (!method_exists($modWorker, $action)) {
                throw new \Exception("Method does not exists: {$action}", 404);
            }
            $log[] = "Run $controller->$action";

            $error = null;
            $out = null;
            try {
                $out = $modWorker->$action($job, $workload->payload);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }

            $db = new Db($config);
            $db->db->update("core_worker_jobs", [
                'time_finish' =>  (new \DateTime())->format("Y-m-d H:i:s"),
                'status'    =>    'finish',
                'error'     =>    $error,
                'executor'  =>    "$controller->$action",
                'data'      =>    $out,
            ], $db->db->quoteInto('id = ?', $id));
            $db->db->closeConnection();

            $log[] = "Finish $controller->$action";

            return $out;
            //}
            //throw new Exception("Worker is broken: " . $action, 500);
        }
        throw new \Exception("Workhorse can't find worker", 500);
        return;
    }

    /**
     * Подключаем модуль, ответственный за выполнение задания
     * @param $module
     * @param $location
     * @return void
     * @throws \Exception
     */
    private function requireController($module, $location) {
        $modWorker = "Mod" . ucfirst(strtolower($module)) . "Worker";
        $controller_path = $location . "/" . $modWorker . ".php";
        if (!file_exists($controller_path)) {
            throw new \Exception("Module worker does not exists: " . $location, 404);
        }
        $autoload = $location . "/vendor/autoload.php";
        if (file_exists($autoload)) { //подключаем автозагрузку если есть
            require_once $autoload;
        }
        require_once $controller_path; // подлючаем контроллер
        if (!class_exists($modWorker)) {
            throw new \Exception("Module worker is broken: " . $location, 500);
        }
        return $modWorker;
    }
}