<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/RedisStreamQueue.php';

use Predis\Client;

class Eventer
{

    private $_config;
    private $client;
    private $queue;

    public function __construct()
    {
        $this->_config = Registry::get('config');
        $core_config = Registry::get('core_config');
        // Инициализация Redis клиента
        $this->client = new Client([
            'host' => $core_config->cache->options->server->host,
            'port' => 6379,
            'password' => $core_config->cache->options->server->password,
            'prefix' => $_SERVER['SERVER_NAME'] . ":Core2:Eventer"
        ]);
        // Создание очереди
        $this->queue = new RedisStreamQueue($this->client, prefix: 'core2_queue');
    }


    /**
     * @param \GearmanJob|Job $job
     * @param array       $log
     */
    public function run(\GearmanJob|Job $job, array &$log) {

        $workload = json_decode($job->workload());

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        $_SERVER = get_object_vars($workload->server);

        $context = $workload->payload->context;
        $event  = $workload->payload->event;
        $data   = is_object($workload->payload->data) ? get_object_vars($workload->payload->data) : $workload->payload->data;

        // Добавить сообщение в очередь
        $this->queue->push([
            'event' => $event,
            'data' => $data,
        ], $context);

    }
}