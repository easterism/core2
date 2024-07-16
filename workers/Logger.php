<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/Log.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';


class Logger
{
    private $_config;
    private $_core_config;
    private $_writer;
    private $_access_files = [];
    private $_system_files = [];

    public function __construct()
    {
        $this->_config = Registry::get('config');
        $this->_core_config = Registry::get('core_config');

        // Журнал работы системы
        if (isset($this->_core_config->log->system->writer) &&
            $this->_core_config->log->system->writer == 'file' &&
            !empty($this->_core_config->log->system->file)
        ) {
            $this->_system_files[] = $this->_core_config->log->system->file;
        }
        //журнал запросов
        if (isset($this->_core_config->log->access->writer) &&
            $this->_core_config->log->system->writer == 'file' &&
            !empty($this->_core_config->log->access->file)
        ) {
            $this->_access_files[] = $this->_core_config->log->access->file;
        }

        // Журнал работы системы уровня conf.ini
        // может использоваться для журнала конкретного хоста
        if (isset($this->_config->log->system->writer) &&
            $this->_config->log->system->writer == 'file' &&
            !empty($this->_config->log->system->file)
        ) {
            if (!in_array($this->_config->log->system->file, $this->_system_files)) $this->_system_files[] = $this->_config->log->system->file;
        }
        //журнал запросов
        if (isset($this->_config->log->access->writer) &&
            $this->_config->log->system->writer == 'file' &&
            !empty($this->_config->log->access->file)
        ) {
            if (!in_array($this->_config->log->system->file, $this->_access_files)) $this->_access_files[] = $this->_config->log->access->file;
        }

    }

    public function run($job, &$log)
    {

        $workload = json_decode($job->workload());
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
            return;
        }

        $_SERVER = get_object_vars($workload->server);

        $data = get_object_vars($workload->payload); //данные для сохранения в базу
        if (isset($data['sid'])) {
            if ($this->_access_files) {
                foreach ($this->_access_files as $access_file) {
                    $corelog = new Log('logger');
                    $corelog->file($access_file)->access($workload->auth->NAME, $workload->payload->sid);
                    $log[] = "ACCESS LOG: " . $access_file;
                }

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
        } else {
            //TODO лог системы
        }
    }
}