<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Registry.php';
require_once __DIR__ . '/../inc/classes/Common.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/I18n.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

class Workhorse
{

    private $_config;

    public function __construct()
    {
        $this->_config = Registry::get('config');
        Registry::set('worker', []);
    }

    public function run(\GearmanJob|Job $job, &$log) {

        $handler = $job->handle();

        $workload = json_decode($job->workload());
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        $_SERVER = get_object_vars($workload->server);
        $id = $_SERVER['SERVER_NAME'] . "|" . $job->unique();

        // Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
        if (!defined("DOC_ROOT")) {
            define("DOC_ROOT", dirname(str_replace("//", "/", $_SERVER['SCRIPT_FILENAME'])) . "/");
        }
        if (!defined("DOC_PATH")) {
            define("DOC_PATH", substr(DOC_ROOT, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))) ? : '/');
        }

        //$workload_size = $job->workloadSize();

        if (!empty($workload->module) && !empty($workload->location) && !empty($workload->worker)) {
            Registry::set('context', [strtolower($workload->module)]);
            Registry::set('auth',  $workload->auth);

            $db = new Db($this->_config);
            $in_job = $db->db->fetchRow("SELECT 1 FROM core_worker_jobs WHERE id=? AND status != 'finish'", $id);
            if ($in_job) {
                //задача уже обрабатывается
                $log[] = "Job {$job->handle()} already in progress";
                return false;
            }
            $job->sendStatus(0, 100);

            $controller = $this->requireController($workload->module, $workload->location);
            $action     = $workload->worker;

            $data = [
                'id' => $id,
                'time_start' => (new \DateTime())->format("Y-m-d H:i:s"),
                'handler' => $handler,
                'status' => 'start',
                'executor' => "$controller->$action",
            ];

            $db->db->insert("core_worker_jobs", $data);

            Registry::set('worker', [
                'request' => $_SERVER['REQUEST_URI'] ?? '',
                'module' => $workload->module,
                'action' => $action,
                'job' => $id,
            ]);

            $error = null;
            $out   = null;

            $modWorker = new $controller();

            if (!method_exists($modWorker, $action)) {
                throw new \Exception("Method does not exists: {$action}", 404);
            }
            $log[] = "Run $controller->$action in context " . $workload->module;
            $job->sendStatus(1, 100);
            //выполнение задачи
            try {
                $out = $modWorker->$action($job, $workload->payload);
                if ($modWorker instanceof Db) {
                    if ($modWorker->db->isConnected()) $modWorker->db->closeConnection();
                };
            } catch (\Exception $e) {
                if ($modWorker instanceof Db) {
                    if ($modWorker->db->isConnected()) $modWorker->db->closeConnection();
                };
                $error = $e->getMessage();
            }

            //$modWorker->db->closeConnection();
            unset($modWorker);
            $job->sendStatus(100, 100);

            $db->db->update("core_worker_jobs", [
                'time_finish' =>  (new \DateTime())->format("Y-m-d H:i:s"),
                'status'    =>    'finish',
                'error'     =>    $error,
                'executor'  =>    "$controller->$action",
                'data'      =>    $out,
            ], [
                $db->db->quoteInto('id = ?', $id),
                $db->db->quoteInto('handler = ?', $handler)
            ]);
            $db->db->closeConnection();

            if ($error) throw new \Exception($error, $e->getCode());

            $log[] = "Finish $controller->$action";

            return $out ?: true; //только не false
            //}
            //throw new Exception("Worker is broken: " . $action, 500);
        }
        throw new \Exception("Workhorse can't find worker", 500);
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