<?php
require_once __DIR__ . '/../inc/classes/Zend_Registry.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

class Workhorse
{
    public function run($job, &$log) {

        $workload = json_decode($job->workload());
        try {
            //$workload_size = $job->workloadSize();
            if (!empty($workload->module) && !empty($workload->location) && !empty($workload->worker)) {
                $this->requireController($workload->module, $workload->location);
                $modWorker = "Mod" . ucfirst(strtolower($workload->module)) . "Worker";
                \Zend_Registry::set('config', unserialize($workload->config));
                \Zend_Registry::set('core_config', new Zend_Config_Ini(__DIR__ . "/../conf.ini", 'production'));
                define("DOC_ROOT", $workload->location);
                $modWorker = new $modWorker();
                $action = $workload->worker;

                if (!method_exists($modWorker, $action)) {
                    throw new Exception("Method does not exists: " . $action, 404);
                }

                //if ($modWorker instanceof Worker) {

                $out = $modWorker->$action($job, $workload->payload);
                $job->sendComplete($out);

                return $out;
                //}
                throw new Exception("Worker is broken: " . $action, 500);
            }
        } catch (\Exception $e) {
            $job->sendException($e->getMessage());
            echo $e->getMessage();
            $log[] = $e->getMessage();
        }

        return;
    }

    private function requireController($module, $location) {
        $modController = "Mod" . ucfirst(strtolower($module)) . "Worker";
        $controller_path = $location . "/" . $modController . ".php";
        if (!file_exists($controller_path)) {
            throw new Exception("Module does not exists: " . $location, 404);
        }
        $autoload = $location . "/vendor/autoload.php";
        if (file_exists($autoload)) { //подключаем автозагрузку если есть
            require_once $autoload;
        }
        require_once $controller_path; // подлючаем контроллер
        if (!class_exists($modController)) {
            throw new Exception("Module is broken: " . $location, 500);
        }
    }
}