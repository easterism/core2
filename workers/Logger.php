<?php

use Core2\Log;

require_once __DIR__ . '/../inc/classes/Zend_Registry.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Log.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

class Logger
{
    public function run($job, &$log) {

        $workload = json_decode($job->workload());
        $job->sendData('start');
        $config = unserialize($workload->config);
        $db = new \Core2\Db($config);
        \Zend_Registry::set('config', $config);
        \Zend_Registry::set('core_config', new Zend_Config_Ini(__DIR__ . "/../conf.ini", 'production'));
        define("DOC_ROOT", $workload->location);
        $_SERVER = get_object_vars($workload->server);

        if (isset($config->log) &&
            $config->log &&
            isset($config->log->system->writer) &&
            $config->log->system->writer == 'file'
        ) {
            if ( ! $config->log->system->file) {
                throw new \Exception('Не задан файл журнала запросов');
            }

            $log = new Log('access');
            $log->access($workload->auth->NAME, $workload->payload->sid);

        } else {
            $data = get_object_vars($workload->payload);
            if ($data['action']) {
                $data['action'] = serialize($data['action']);
            }
            $db->db->insert('core_log', $data);
        }

        // обновление записи о последней активности
        if ($workload->auth->LIVEID) {
            $row = $db->dataSession->find($workload->auth->LIVEID)->current();

            if ($row) {
                $row->last_activity = new \Zend_Db_Expr('NOW()');
                $row->save();
            }
        }

        $job->sendComplete('done');
    }
}