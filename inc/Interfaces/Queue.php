<?php
declare(strict_types=1);
namespace Core2;
/**
 * Очереди Redis с гарантированной доставкой
 */
interface Queue
{

    public function push(array $payload, string $queue = 'default'): bool;
    public function pop(string $queue = 'default', int $timeout = 0): ?array;
    public function acknowledge(string $queue, string $messageId): bool;
    public function reject(string $queue, string $messageId, bool $requeue = false): bool;
    public function getPendingCount(string $queue): int;
    public function getQueueSize(string $queue): int;
}