<?php
declare(strict_types=1);
namespace Core2;

class TokenBucket
{
    /** @var string Директория для хранения состояния бакетов */
    private string $storageDir;

    /** @var int Максимальное количество токенов в бакете */
    private int $capacity;

    /** @var float Скорость пополнения (токенов в секунду) */
    private float $refillRate;

    /** @var int Время жизни файла состояния (сек), для очистки старых бакетов */
    private int $ttl;

    /**
     * @param int   $capacity   Максимум токенов в бакете
     * @param float $refillRate Сколько токенов добавляется в секунду
     * @param string|null $storageDir Директория для хранения состояния
     * @param int   $ttl        Время жизни файла (секунды)
     */
    public function __construct(
        int $capacity = 10,
        float $refillRate = 1.0,
        ?string $storageDir = null,
        int $ttl = 3600
    ) {
        if ($capacity <= 0) {
            throw new \InvalidArgumentException('Capacity must be > 0');
        }
        if ($refillRate <= 0) {
            throw new \InvalidArgumentException('Refill rate must be > 0');
        }

        $this->capacity   = $capacity;
        $this->refillRate = $refillRate;
        $this->ttl        = $ttl;
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/token_buckets';

        if (!is_dir($this->storageDir)) {
            if (!mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
                throw new \RuntimeException("Cannot create storage dir: {$this->storageDir}");
            }
        }
    }

    /**
     * Проверяет, можно ли выполнить запрос, и если да — списывает 1 токен.
     *
     * @param string $token   Идентификатор клиента (API-ключ, IP и т.д.)
     * @param int    $cost    Сколько токенов списать (по умолчанию 1)
     * @return bool           true — запрос разрешён, false — лимит исчерпан
     */
    public function consume(string $token, int $cost = 1): bool
    {
        if ($cost <= 0) {
            throw new \InvalidArgumentException('Cost must be > 0');
        }

        return $this->withLock($token, function (array &$state) use ($cost): bool {
            $this->refill($state);

            if ($state['tokens'] >= $cost) {
                $state['tokens'] -= $cost;
                return true;
            }

            return false;
        });
    }

    /**
     * Добавляет токены в бакет вручную
     *
     * @param string $token     Идентификатор клиента
     * @param int    $amount    Сколько токенов добавить
     * @param bool   $capAtMax  Ограничивать ли максимумом capacity
     * @return float            Итоговое количество токенов в бакете
     */
    public function addTokens(string $token, int $amount, bool $capAtMax = true): float
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be > 0');
        }

        $result = 0.0;
        $this->withLock($token, function (array &$state) use ($amount, $capAtMax, &$result): bool {
            $this->refill($state);

            $state['tokens'] += $amount;
            if ($capAtMax && $state['tokens'] > $this->capacity) {
                $state['tokens'] = (float) $this->capacity;
            }

            $result = $state['tokens'];
            return false; // ничего не списываем, но фиксируем запись
        });

        return $result;
    }

    /**
     * Устанавливает точное количество токенов (полезно для тестов/сброса).
     */
    public function setTokens(string $token, float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be >= 0');
        }

        $this->withLock($token, function (array &$state) use ($amount): bool {
            $state['tokens']   = min($amount, (float) $this->capacity);
            $state['updated']  = microtime(true);
            return false;
        });
    }

    /**
     * Возвращает текущее количество токенов, не списывая их.
     */
    public function getTokens(string $token): float
    {
        $result = 0.0;
        $this->withLock($token, function (array &$state) use (&$result): bool {
            $this->refill($state);
            $result = $state['tokens'];
            return false;
        });

        return $result;
    }

    /**
     * Возвращает секунды до момента, когда появится нужное количество токенов.
     */
    public function getRetryAfter(string $token, int $cost = 1): float
    {
        $needed = 0.0;
        $this->withLock($token, function (array &$state) use ($cost, &$needed): bool {
            $this->refill($state);
            if ($state['tokens'] >= $cost) {
                $needed = 0.0;
            } else {
                $missing = $cost - $state['tokens'];
                $needed  = $missing / $this->refillRate;
            }
            return false;
        });

        return $needed;
    }

    /**
     * Пополняет бакет в зависимости от прошедшего времени.
     */
    private function refill(array &$state): void
    {
        $now     = microtime(true);
        $elapsed = $now - $state['updated'];

        if ($elapsed <= 0) {
            return;
        }

        $state['tokens'] = min(
            (float) $this->capacity,
            $state['tokens'] + $elapsed * $this->refillRate
        );
        $state['updated'] = $now;
    }

    /**
     * Атомарно читает, изменяет и пишет состояние бакета под блокировкой.
     *
     * @param callable $mutator function(array &$state): bool  должен вернуть true, если состояние изменено
     */
    private function withLock(string $token, callable $mutator): bool
    {
        $file = $this->getFilePath($token);
        $fp   = fopen($file, 'c+');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open bucket file: {$file}");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException("Cannot lock bucket file: {$file}");
            }

            $raw   = stream_get_contents($fp);
            $state = $this->decodeState($raw);

            $mutated = $mutator($state);

            // Записываем всегда — обновляем updated/tokens
            $this->writeState($fp, $state);

            flock($fp, LOCK_UN);

            return $mutated;
        } finally {
            fclose($fp);
        }
    }

    private function decodeState(string $raw): array
    {
        if ($raw === '' || $raw === false) {
            return [
                'tokens'  => (float) $this->capacity,
                'updated' => microtime(true),
            ];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['tokens'], $data['updated'])) {
            return [
                'tokens'  => (float) $this->capacity,
                'updated' => microtime(true),
            ];
        }

        return [
            'tokens'  => (float) $data['tokens'],
            'updated' => (float) $data['updated'],
        ];
    }

    private function writeState($fp, array $state): void
    {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state, JSON_THROW_ON_ERROR));
        fflush($fp);
    }

    private function getFilePath(string $token): string
    {
        // Хэш, чтобы избежать проблем с недопустимыми символами в имени файла
        $hash = hash('sha256', $token);
        return $this->storageDir . '/' . $hash . '.bucket';
    }

    /**
     * Удаляет файлы состояния, которые не обновлялись дольше TTL.
     * Вызывайте периодически (например, по крону).
     */
    public function cleanup(): int
    {
        $deleted = 0;
        foreach (glob($this->storageDir . '/*.bucket') ?: [] as $file) {
            if (is_file($file) && (time() - filemtime($file)) > $this->ttl) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }
}