<?php
declare(strict_types=1);
namespace Core2;

require_once __DIR__ . '/../Interfaces/Queue.php';

use Predis\Client;
use Exception;

class RedisStreamQueue implements Queue
{
    private Client $client;
    private string $prefix;
    private string $consumerGroup;
    private string $consumerName;

    public function __construct(
        Client $client,
        string $prefix = 'stream',
        string $consumerGroup = 'workers',
        string $consumerName = 'worker-1'
    ) {
        $this->client = $client;
        $this->prefix = $prefix;
        $this->consumerGroup = $consumerGroup;
        $this->consumerName = $consumerName;
    }

    /**
     * Добавить сообщение в поток
     */
    public function push(array $payload, string $queue = 'default'): bool
    {
        try {
            $streamName = $this->getStreamName($queue);

            // 🔧 Все значения должны быть строками!
            $fields = [
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => (string) time(),
                'attempts' => '0'
            ];

            $args = $this->buildXaddCommand($streamName, $fields);
            $messageId = $this->client->executeRaw($args);

            return $messageId !== false && $messageId !== null;
        } catch (Exception $e) {
            error_log("RedisStreamQueue push error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔧 Построить команду XADD вручную
     */
    private function buildXaddCommand(string $streamName, array $fields): array
    {
        $args = ['XADD', $streamName, '*'];

        foreach ($fields as $field => $value) {
            // 🔧 Гарантируем что всё - строки
            $args[] = (string) $field;
            $args[] = (string) $value;
        }

        return $args;
    }

    /**
     * Получить сообщение из потока (с Consumer Group)
     */
    public function pop(string $queue = 'default', int $timeout = 1000): ?array
    {
        try {
            $streamName = $this->getStreamName($queue);

            // Создаём Consumer Group если не существует
            $this->createConsumerGroup($streamName);

            // 🔧 xreadgroup - правильный синтаксис
            $messages = $this->client->xreadgroup(
                'GROUP', $this->consumerGroup, $this->consumerName,
                'COUNT', 1,
                'BLOCK', $timeout,
                'STREAMS', $streamName, '>'
            );

            if (empty($messages) || !isset($messages[$streamName])) {
                return null;
            }

            $messageData = reset($messages[$streamName]);
            $messageId = key($messages[$streamName]);

            // 🔧 Декодируем payload
            $payload = is_string($messageData['payload'])
                ? json_decode($messageData['payload'], true)
                : $messageData['payload'];

            if ($payload === null) {
                $payload = $messageData;
            }

            $payload['message_id'] = $messageId;
            $payload['attempts'] = (int) ($messageData['attempts'] ?? 0) + 1;

            return $payload;
        } catch (Exception $e) {
            error_log("RedisStreamQueue pop error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Подтвердить обработку (XACK)
     */
    public function acknowledge(string $queue, string $messageId): bool
    {
        try {
            $streamName = $this->getStreamName($queue);
            // 🔧 xack ожидает: stream, group, id1, id2, ...
            $this->client->xack($streamName, $this->consumerGroup, $messageId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Отклонить сообщение
     */
    public function reject(string $queue, string $messageId, bool $requeue = false): bool
    {
        try {
            $streamName = $this->getStreamName($queue);

            if ($requeue) {
                // Возвращаем в pending для повторной обработки
                $this->client->xclaim(
                    $streamName,
                    $this->consumerGroup,
                    $this->consumerName,
                    0,
                    $messageId,
                    'JUSTID'
                );
            } else {
                // Перемещаем в DLQ stream
                $this->moveToDLQ($queue, $messageId);
                $this->acknowledge($queue, $messageId);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Получить количество pending сообщений
     */
    public function getPendingCount(string $queue): int
    {
        try {
            $streamName = $this->getStreamName($queue);
            $pending = $this->client->xpending($streamName, $this->consumerGroup);
            return (int) ($pending[1] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Получить размер потока
     */
    public function getQueueSize(string $queue): int
    {
        try {
            $streamName = $this->getStreamName($queue);
            $info = $this->client->xinfo('STREAM', $streamName);

            // Находим индекс длины потока
            for ($i = 0; $i < count($info); $i += 2) {
                if ($info[$i] === 'length') {
                    return (int) $info[$i + 1];
                }
            }
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Обработать зависшие сообщения (recovery)
     */
    public function recoverPendingMessages(string $queue, int $minIdleTime = 3600000): int
    {
        try {
            $streamName = $this->getStreamName($queue);

            // Получаем ID зависших сообщений
            $pending = $this->client->xpending($streamName, $this->consumerGroup, '-', '+', 100);

            $recovered = 0;
            foreach ($pending as $message) {
                $idleTime = $message[3] ?? 0;

                if ($idleTime >= $minIdleTime) {
                    $messageId = $message[0];

                    //_claim_ возвращает сообщение обратно в обработку
                    $this->client->xclaim(
                        $streamName,
                        $this->consumerGroup,
                        $this->consumerName,
                        $minIdleTime,
                        $messageId
                    );
                    $recovered++;
                }
            }

            return $recovered;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Получить Dead Letter сообщения
     */
    public function getDLQMessages(string $queue, int $count = 10): array
    {
        try {
            $dlqName = $this->getDLQName($queue);
            $messages = $this->client->xrevrange($dlqName, '+', '-', 'COUNT', $count);

            $result = [];
            foreach ($messages as $messageId => $data) {
                $result[] = [
                    'message_id' => $messageId,
                    'payload' => json_decode($data['payload'], true),
                    'error' => $data['error'] ?? null,
                    'failed_at' => $data['failed_at'] ?? null
                ];
            }

            return $result;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Повторить сообщение из DLQ
     */
    public function retryFromDLQ(string $queue, string $messageId): bool
    {
        try {
            $dlqName = $this->getDLQName($queue);
            $streamName = $this->getStreamName($queue);

            $messages = $this->client->xrange($dlqName, '-', '+');

            foreach ($messages as $id => $data) {
                if ($id === $messageId) {
                    // Добавляем обратно в основной поток
                    $args = $this->buildXaddCommand($streamName, ['payload' => $data['payload'], 'attempts' => 0]);
                    $this->client->executeRaw($args);

                    // Удаляем из DLQ
                    $this->client->xdel($dlqName, $messageId);
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getStreamName(string $queue): string
    {
        return "{$this->prefix}:{$queue}";
    }

    private function getDLQName(string $queue): string
    {
        return "{$this->prefix}:{$queue}:dlq";
    }

    private function createConsumerGroup(string $streamName): void
    {
        try {
            $this->client->xgroup('CREATE', $streamName, $this->consumerGroup, '0', 'MKSTREAM');
        } catch (Exception $e) {
            // Группа уже существует - игнорируем
        }
    }

    private function moveToDLQ(string $queue, string $messageId): void
    {
        try {
            $streamName = $this->getStreamName($queue);
            $dlqName = $this->getDLQName($queue);

            $messages = $this->client->xrange($streamName, $messageId, $messageId);

            if (!empty($messages)) {
                $data = reset($messages);
                $args = $this->buildXaddCommand($dlqName, [
                    'payload' => $data['payload'],
                    'original_id' => $messageId,
                    'failed_at' => (string) time(),
                    'error' => 'Manual reject to DLQ'
                ]);
                $this->client->executeRaw($args);
            }
        } catch (Exception $e) {
            // Игнорируем ошибки DLQ
        }
    }
}