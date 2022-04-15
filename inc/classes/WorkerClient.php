<?php
namespace Core2;

use Laminas\Session\Container as SessionContainer;


/**
 * Class WorkerClient
 */
class WorkerClient {
    private $client;
    private $db;
    private $location;
    private $module;

    public function __construct($db) {

        $this->client = new \GearmanClient();
        try {
            $this->client->addServer('127.0.0.1', '4730');
        } catch (\GearmanException $e) {
            return new \stdObject();
        }

        $this->db = $db;

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

    public function setModule($module) {
        $this->module = $module;
    }

    public function setLocation($loc) {
        $this->location = $loc;
    }

    public function doBackground($worker, $data, $unique = null) {
        if ($this->module === 'Admin') {
            $auth = new SessionContainer('Auth');
            $data = ['location' => $this->location,
                'config' => serialize(\Zend_Registry::get('config')),
                'server' => $_SERVER['SERVER_NAME'],
                'auth' => $auth->getArrayCopy(),
                'payload' => $data];
            $jh = $this->client->doBackground($worker, json_encode($data), $unique);
            if ($this->client->returnCode() != GEARMAN_SUCCESS)
            {
                return false;
            }
            return $jh;
        }
        $data = ['module' => $this->module,
            'location' => $this->location,
            'config' => serialize(\Zend_Registry::get('config')),
            'worker' => $worker,
            'server' => $_SERVER['SERVER_NAME'],
            'payload' => $data];
        $jh = $this->client->doBackground("Workhorse", json_encode($data), $unique);
        if ($this->client->returnCode() != GEARMAN_SUCCESS)
        {
            return false;
        }
        return $jh;
    }

    public function jobStatus($job_handle) {
        return $this->client->jobStatus($job_handle);
    }

    public function error() {
        return $this->client->getErrno();
    }
}