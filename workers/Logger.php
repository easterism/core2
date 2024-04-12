<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Log.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

use Core2\Log;

class Logger
{
    private $_config;
    private $_writer;

    public function __construct()
    {
        $this->_config = Registry::get('config');

        if (isset($this->_config->log) &&
            $this->_config->log &&
            isset($this->_config->log->system->writer) &&
            $this->_config->log->system->writer == 'file'
        ) {
            if ( ! $this->_config->log->system->file) {
                throw new \Exception('Не задан файл журнала запросов');
            }
            $this->_writer = 'file';

        } else {
            $this->_writer = 'db';
        }
    }

    public function run($job, &$log) {

        $workload = json_decode($job->workload());
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
            return;
        }

        $_SERVER = get_object_vars($workload->server);

        $data = []; //данные для сохранения в базу
        if ($this->_writer == 'file') {
            $log[] = "Запись в файл " . $this->_config->log->system->file;

            $corelog = new Log('access');
            $corelog->access($workload->auth->NAME, $workload->payload->sid);

        } else {
            $data = get_object_vars($workload->payload);
            if ($data['action']) {
                $data['action'] = serialize($data['action']);
            }
            //$log[] = "Соединяемся с базой...";
            $mysql = (new Db())->db;
            try {
                $mysql->insert('core_log', $data);

                //$log[] = "закрываем соединение...";
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