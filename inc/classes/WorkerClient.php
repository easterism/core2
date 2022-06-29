<?php
namespace Core2;

use Laminas\Session\Container as SessionContainer;


/**
 * Class WorkerClient
 */
class WorkerClient {

    private $client;
    private $location;
    private $module;

  public function __construct() {

        $cc = \Zend_Registry::get('core_config');

        if ($host = trim($cc->gearman->host)) {
            if (!class_exists('\\GearmanClient')) throw new \Exception('Class GearmanClient not found');
            $this->client = new \GearmanClient();
            //$this->client->addOptions(GEARMAN_CLIENT_NON_BLOCKING);
            try {
                //$this->assignCallbacks();
                $this->client->addServers($host);
            } catch (\GearmanException $e) {
                return new \stdObject();
            }
        } else { //TODO другие воркеры?
            return new \stdObject();
        }

        return $this;

        //$stat = $client->jobStatus($job_handle);
        //echo "<PRE>Код: ";print_r($client->returnCode());echo "</PRE>";//die;

        //$job_handle = $client->doBackground('reverse', json_encode($data));


        # Добавление задачи для функции reverse
        //$task= $client->addTask("reverse", "Hello World!", null, "1");
//                if ($_GET['status']) {
//                    $stat = $client->jobStatus("H:zend-server.rdo.belhard.com:" . $_GET['status']);
//                    echo "<PRE>";print_r($stat);echo "</PRE>";//die;
//                    $stat = $client->jobStatus("H:zend-server.rdo.belhard.com:" . ($_GET['status'] + 1));
//                    echo "<PRE>";print_r($stat);echo "</PRE>";die;
//                }
        # Установка нескольких callback-функций. Таким образом, мы сможем отслеживать выполнение
        //$client->setCompleteCallback("reverse_complete");
        //$client->setStatusCallback("reverse_status");
        //$client->setCreatedCallback(function ($task) {
        //    var_dump($task->jobHandle()); // "H:server:1"
        //});
        # Добавление другой задачи, но она предназначена для запуска в фоновом режиме
        //$client->addTaskBackground("Logger", $_SERVER, null, "1");
        //if (! $client->runTasks())
        //{
        //    echo "Ошибка " . $client->error() . "\n";
        //    exit;
        //}
    }

    private function assignCallbacks() {
        $this->client->setExceptionCallback(function (\GearmanTask $task) {
            echo "<PRE>!!";print_r($task);echo "</PRE>";die;
        });
    }

    public function setModule($module) {
        $this->module = $module;
    }

    public function setLocation($loc) {
        $this->location = $loc;
    }

    /**
     * Запускает выполнение задачи в фоновом режиме
     * @param $worker
     * @param $data
     * @param $unique
     * @return false|string
     */
    public function doBackground($worker, $data, $unique = null) {
        $worker = $this->getWorkerName($worker);
        $workload = $this->getWorkload($worker, $data);
        $jh = $this->client->doBackground($worker, $workload, $unique);
        if ($this->client->returnCode() != GEARMAN_SUCCESS)
        {
            return false;
        }
        return $jh;
    }

    /**
     * Запускает на выполнение с высоким приоритетом задачу в фоновом режиме
     * @param $worker
     * @param $data
     * @param $unique
     * @return false|string
     */
    public function doHighBackground($worker, $data, $unique = null) {
        $worker = $this->getWorkerName($worker);
        $workload = $this->getWorkload($worker, $data);
        $jh = $this->client->doHighBackground($worker, $workload, $unique);
        if ($this->client->returnCode() != GEARMAN_SUCCESS)
        {
            return false;
        }
        return $jh;
    }

    /**
     * @param $worker
     * @param $data
     * @return false|string
     */
    private function getWorkload($worker, $data) {
        $auth = new SessionContainer('Auth');
        if ($this->module === 'Admin') {
            $workload = [
                'location' => $this->location,
                'config'   => serialize(\Zend_Registry::get('config')),
                'server'   => $_SERVER,
                'auth'     => $auth->getArrayCopy(),
                'payload'  => $data
            ];

        } else {
            $workload = [
                'module'    => $this->module,
                'location'  => $this->location,
                'doc_root'  => DOC_ROOT,
                'config'    => serialize(\Zend_Registry::get('config')),
                'context'   => \Zend_Registry::get('context'),
                'translate' => serialize(\Zend_Registry::get('translate')),
                'worker'    => $worker,
                'server'    => $_SERVER,
                'auth'      => $auth->getArrayCopy(),
                'payload'   => $data
            ];
        }
        return json_encode($workload);
    }

    public function jobStatus($job_handle) {
        return $this->client->jobStatus($job_handle);
    }

    public function error() {
        return $this->client->getErrno();
    }

    private function getWorkerName($worker) {
        $dd = str_replace(DIRECTORY_SEPARATOR, "-", dirname(dirname(dirname(__DIR__))));
        $dd = trim($dd, '-');
        if ($this->module !== 'Admin') $worker = "Workhorse";
        return $dd . "-" . $worker;
    }
}