<?php
require_once __DIR__ . '/../inc/classes/Zend_Registry.php';
require_once __DIR__ . '/../inc/classes/Common.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/I18n.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

class Workhorse
{
    public function run($job, &$log) {
        $job->sendData('start');
        $workload = json_decode($job->workload());
        try {
            //$workload_size = $job->workloadSize();
            if (!empty($workload->module) && !empty($workload->location) && !empty($workload->worker)) {
                $modWorker = $this->requireController($workload->module, $workload->location);

                \Zend_Registry::set('config',      unserialize($workload->config));
                \Zend_Registry::set('translate',   unserialize($workload->translate));
                \Zend_Registry::set('context',     $workload->context);
                \Zend_Registry::set('auth',        $workload->auth);
                \Zend_Registry::set('core_config', new Zend_Config_Ini(__DIR__ . "/../conf.ini", 'production'));


                define("DOC_ROOT", $workload->doc_root);
                $modWorker = new $modWorker();
                $action = $workload->worker;

                if (!method_exists($modWorker, $action)) {
                    throw new Exception("Method does not exists: {$action}", 404);
                }

                //if ($modWorker instanceof Worker) {

                $out = $modWorker->$action($job, $workload->payload);
                $job->sendComplete($out);

                return $out;
                //}
                //throw new Exception("Worker is broken: " . $action, 500);
            }
        } catch (\Exception $e) {
            $job->sendException($e->getMessage());
            echo $e->getMessage();
            $log[] = $e->getMessage();
        }
        return;
    }

    /**
     * Подключаем модуль, ответственный за выполнение задания
     * @param $module
     * @param $location
     * @return void
     * @throws Exception
     */
    private function requireController($module, $location) {
        $modWorker = "Mod" . ucfirst(strtolower($module)) . "Worker";
        $controller_path = $location . "/" . $modWorker . ".php";
        if (!file_exists($controller_path)) {
            throw new Exception("Module worker does not exists: " . $location, 404);
        }
        $autoload = $location . "/vendor/autoload.php";
        if (file_exists($autoload)) { //подключаем автозагрузку если есть
            require_once $autoload;
        }
        require_once $controller_path; // подлючаем контроллер
        if (!class_exists($modWorker)) {
            throw new Exception("Module worker is broken: " . $location, 500);
        }
        return $modWorker;
    }
}