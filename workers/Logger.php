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

        $config = unserialize($workload->config);

        \Zend_Registry::set('config', $config);
        \Zend_Registry::set('core_config', new Zend_Config_Ini(__DIR__ . "/../conf.ini", 'production'));
        define("DOC_ROOT", $workload->location);
        $_SERVER = get_object_vars($workload->server);

        $data = []; //данные для сохранения в базу
        if (isset($config->log) &&
            $config->log &&
            isset($config->log->system->writer) &&
            $config->log->system->writer == 'file'
        ) {
            if ( ! $config->log->system->file) {
                throw new \Exception('Не задан файл журнала запросов');
            }
            $log[] = "Запись в файл " . $config->log->system->file;

            $corelog = new Log('access');
            $corelog->access($workload->auth->NAME, $workload->payload->sid);

        } else {
            $data = get_object_vars($workload->payload);
            if ($data['action']) {
                $data['action'] = serialize($data['action']);
            }
        }

        // обновление записи о последней активности
        if ($workload->auth->LIVEID || $data) {
            $log[] = "Запись в базу сведений о последней активности";
            $db = new \Core2\Db($config);
            //$log[] = "Соединяемся с базой...";
            $mysql = $db->db;
            try {
                if ($data) $mysql->insert('core_log', $data);
                $row = $db->dataSession->find($workload->auth->LIVEID)->current();
                if ($row) {
                    $row->last_activity = new \Zend_Db_Expr('NOW()');
                    $row->save();
                }
                $log[] = "закрываем соединение...";
                $mysql->closeConnection();
            } catch (\Exception $e) {
                // игнорируем исключение
                $log[] = $e->getMessage();
                $mysql->closeConnection();
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }
        return;
    }
}