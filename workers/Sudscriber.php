<?php

namespace Core2;

require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Db.php';
require_once __DIR__ . '/../inc/classes/RedisStreamQueue.php';

use Predis\Client;

class Subscriber
{
    private $_config;
    private $_log;
    private $client;
    private $queue;
    private $streamName = 'subscriptions'; // Имя потока для подписок

    const LOG_LEVEL_INFO = "info";
    const LOG_LEVEL_ERROR = "error";

    public function __construct()
    {
        $this->_config = Registry::get('config');
        $core_config = Registry::get('core_config');

        return; //пока не нужен

        // Инициализация Redis клиента (аналогично Eventer)
        $this->client = new Client([
            'host' => $core_config->cache->options->server->host,
            'port' => 6379,
            'password' => $core_config->cache->options->server->password,
            'prefix' => $_SERVER['SERVER_NAME'] . ":Core2:Subscriber"
        ]);

        // Инициализация очереди с поддержкой Consumer Groups
        // используем префикс для подписчиков и группу 'subscription_processors'
        $this->queue = new RedisStreamQueue(
            $this->client,
            prefix: 'core2_sub',
            consumerGroup: 'subscription_processors',
            consumerName: 'sub_worker_' . getmypid()
        );
        $this->_log = new Log();
    }

    /**
     * Основной метод выполнения воркера.
     * В этой реализации он работает в бесконечном цикле, потребляя сообщения из стрима.
     *
     * @param \GearmanJob|Job $job (не используется напрямую для получения данных, так как мы читаем из стрима)
     * @param array           &$log
     */
    public function run(\GearmanJob|Job $job, array &$log)
    {
        return;  //пока не нужен

        $this->toLog("Worker started. Listening on stream: {$this->streamName}", self::LOG_LEVEL_INFO);

        while (true) {
            // Получаем сообщение из Redis Stream (блокирующее чтение на 10 секунд)
            $message = $this->queue->pop($this->streamName, 10000);

            if ($message === null) {
                continue; // Если сообщений нет, продолжаем цикл
            }

            try {
                $data = $message['payload'];
                $messageId = $message['message_id'];

                // --- ЛОГИКА ОБРАБОТКИ ПОДПИСКИ ---

                $this->toLog("Processed message $messageId: " . json_encode($data), self::LOG_LEVEL_INFO);

                // Подтверждаем успешную обработку сообщения
                $this->queue->acknowledge($this->streamName, $messageId);

            } catch (\Exception $e) {
                $this->toLog("Error processing message: " . $e->getMessage(), self::LOG_LEVEL_ERROR);

                // В случае ошибки отклоняем сообщение.
                // Если requeue=true, оно вернется в группу для повторной попытки.
                $this->queue->reject($this->streamName, $message['message_id'], true);
            }
        }
    }

    /**
     * Вспомогательный метод логирования
     */
    private function toLog(string $message, string $level)
    {
        $this->_log->$level("[Subscriber] Level $level: $message");
    }

}
