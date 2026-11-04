<?php
namespace Core2;

require_once "Db.php";

/**
 * Буфер данных о сессиях пользователей (MySQL-независимый).
 *
 * Данные о входе/активности/выходе сохраняются в memcached. Способ сброса
 * в таблицу core_session зависит от наличия модуля cron:
 *  - если модуль cron УСТАНОВЛЕН — сброс выполняет задача планировщика
 *    (см. ModAdminCli::syncSessions());
 *  - если модуль cron НЕ установлен — работает "ленивое" сохранение: при
 *    каждой записи проверяется возраст существующей записи в memcached,
 *    и если она старше 1 минуты, запись сбрасывается в базу.
 */
class SessionStore extends Db {

    const TTL        = 86400;
    const SYNC_DELAY = 60;
    const SEQ_BASE   = 1000000000;


    /**
     * @var \Memcached|null
     */
    private $mc;

    /**
     * @var bool|null
     */
    private static $cron_installed = null;


    /**
     * @param \Memcached|null $mc
     */
    public function __construct(?\Memcached $mc = null) {
        $this->mc = $mc;
    }


    /**
     * @return \Memcached
     */
    private function mc(): \Memcached {

        if ($this->mc === null) {
            if ( ! class_exists('\Memcached')) {
                throw new \RuntimeException('Memcached extension is not available');
            }

            $mc = new \Memcached();
            $mc->addServer('localhost', 11211);
            $mc->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 1000);
            $mc->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

            $this->mc = $mc;
        }

        return $this->mc;
    }


    /**
     * Установлен ли модуль cron.
     *
     * @return bool
     */
    private function isCronInstalled(): bool {

        if (self::$cron_installed !== null) {
            return self::$cron_installed;
        }

        try {
            self::$cron_installed = (bool)$this->isModuleActive('cron');

        } catch (\Throwable $e) {
            self::$cron_installed = false;
        }

        return self::$cron_installed;
    }


    /**
     * @return string
     */
    private function prefix(): string {
        return (string)($_SERVER['SERVER_NAME'] ?? '');
    }


    /**
     * @param int $id
     * @return string
     */
    private function dataKey(int $id): string {
        return 'sess_' . md5($this->prefix() . '_' . $id);
    }


    /**
     * @return string
     */
    private function indexKey(): string {
        return 'sess_index_' . md5($this->prefix());
    }


    /**
     * @return string
     */
    private function seqKey(): string {
        return 'sess_seq_' . md5($this->prefix());
    }


    /**
     * Локальный числовой идентификатор сессии (LIVEID).
     * Совпадает с id, под которым запись будет вставлена в core_session.
     *
     * @return int
     */
    public function nextId(): int {

        $mc = $this->mc();
        $id = $mc->increment($this->seqKey(), 1);

        if ($id === false) {
            $base = self::SEQ_BASE;

            try {
                $max = (int)$this->db->fetchOne("SELECT MAX(id) FROM core_session");
                if ($max >= $base) {
                    $base = $max + 1;
                }
            } catch (\Throwable $e) {
                $base = max($base, time());
            }

            if ($mc->add($this->seqKey(), $base)) {
                $id = $base;
            } else {
                $id = $mc->increment($this->seqKey(), 1);
            }
        }

        return (int)$id;
    }


    /**
     * @param array $data
     * @return int
     */
    public function save(array $data): int {

        $mc  = $this->mc();
        $id  = ! empty($data['id']) ? (int)$data['id'] : $this->nextId();
        $now = date('Y-m-d H:i:s');

        $existing = $this->get($id);

        $record = [
            'id'            => $id,
            'sid'           => (string)($data['sid'] ?? ''),
            'user_id'       => (int)($data['user_id'] ?? 0),
            'ip'            => (string)($data['ip'] ?? ''),
            'login_time'    => (string)($data['login_time'] ?? $now),
            'last_activity' => (string)($data['last_activity'] ?? $now),
            'logout_time'   => $data['logout_time'] ?? null,
            'is_expired_sw' => (string)($data['is_expired_sw'] ?? 'N'),
            'is_kicked_sw'  => (string)($data['is_kicked_sw'] ?? 'N'),
            'synced_at'     => $existing ? (int)($existing['synced_at'] ?? time()) : time(),
        ];

        $mc->set($this->dataKey($id), $record, self::TTL);
        $this->indexAdd($id);
        $this->syncIfDue($record);

        return $id;
    }


    /**
     * @param int $id
     * @return array|null
     */
    public function get(int $id): ?array {

        $record = $this->mc()->get($this->dataKey($id));

        return is_array($record) ? $record : null;
    }


    /**
     * @param int   $id
     * @param array $patch
     * @return bool
     */
    public function update(int $id, array $patch): bool {

        $record = $this->get($id);

        if ( ! $record) {
            return false;
        }

        foreach ($patch as $key => $value) {
            $record[$key] = $value;
        }

        $this->mc()->set($this->dataKey($id), $record, self::TTL);
        $this->syncIfDue($record);

        return true;
    }


    /**
     * Отметить активность сессии (не чаще SYNC_DELAY секунд).
     * Обновляет только запись в memcached; в базу данные попадают через
     * механизм синхронизации (ModAdminCli::syncSessions или ленивый сброс).
     *
     * @param int $id
     * @return bool
     */
    public function touch(int $id): bool {

        $id = (int)$id;

        if ( ! $id) {
            return false;
        }

        $mc  = $this->mc();
        $key = 'act_' . md5($this->prefix() . '_' . $id);

        if ( ! $mc->add($key, 1, self::SYNC_DELAY)) {
            return false; //обновляем активность раз в минуту
        }

        return $this->update($id, ['last_activity' => date('Y-m-d H:i:s')]);
    }


    /**
     * @param int $id
     * @return void
     */
    public function remove(int $id): void {

        $this->mc()->delete($this->dataKey($id));
        $this->indexRemove($id);
    }


    /**
     * "Ленивое" сохранение: если модуль cron не установлен и существующая
     * запись в memcached старше SYNC_DELAY секунд — сбросить её в базу.
     *
     * @param array $record
     * @return void
     */
    private function syncIfDue(array $record): void {

        if ($this->isCronInstalled()) {
            return;
        }

        $id = (int)($record['id'] ?? 0);

        if ( ! $id) {
            return;
        }

        $finished = ! empty($record['logout_time']) ||
                    (string)($record['is_expired_sw'] ?? 'N') === 'Y' ||
                    (string)($record['is_kicked_sw'] ?? 'N') === 'Y';

        // завершённую сессию сохраняем сразу, активную — не чаще раза в минуту
        if ( ! $finished && time() - (int)($record['synced_at'] ?? 0) < self::SYNC_DELAY) {
            return;
        }

        if ($this->writeToDb($record)) {
            if ($finished) {
                $this->remove($id);
            } else {
                $record['synced_at'] = time();
                $this->mc()->set($this->dataKey($id), $record, self::TTL);
            }
        }
    }


    /**
     * Сбрасывает все накопленные записи в MySQL (используется cron-задачей).
     *
     * @return int количество обработанных записей
     */
    public function flush(): int {

        $ids = $this->indexList();

        if (empty($ids)) {
            return 0;
        }

        $done = 0;

        foreach ($ids as $id) {
            $record = $this->get($id);

            if ( ! $record) {
                $this->indexRemove($id);
                continue;
            }

            if ( ! $this->writeToDb($record)) {
                break;
            }

            $done++;

            if ( ! empty($record['logout_time']) ||
                 (string)$record['is_expired_sw'] === 'Y' ||
                 (string)$record['is_kicked_sw'] === 'Y'
            ) {
                $this->remove($id);
            } else {
                $record['synced_at'] = time();
                $this->mc()->set($this->dataKey($id), $record, self::TTL);
            }
        }

        return $done;
    }


    /**
     * @param array $record
     * @return bool
     */
    private function writeToDb(array $record): bool {

        try {

            $this->db->query("
                INSERT INTO core_session
                    (id, sid, login_time, logout_time, user_id, ip, is_expired_sw, last_activity, is_kicked_sw)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    sid           = VALUES(sid),
                    login_time    = VALUES(login_time),
                    logout_time   = VALUES(logout_time),
                    user_id       = VALUES(user_id),
                    ip            = VALUES(ip),
                    is_expired_sw = VALUES(is_expired_sw),
                    last_activity = VALUES(last_activity),
                    is_kicked_sw  = VALUES(is_kicked_sw)
            ", [
                (int)$record['id'],
                (string)$record['sid'],
                (string)$record['login_time'],
                $record['logout_time'],
                (int)$record['user_id'],
                (string)$record['ip'],
                (string)$record['is_expired_sw'],
                (string)$record['last_activity'],
                (string)$record['is_kicked_sw'],
            ]);

        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }


    /**
     * @return array
     */
    private function indexList(): array {

        $list = $this->mc()->get($this->indexKey());

        return is_array($list) ? $list : [];
    }


    /**
     * @param int $id
     * @return void
     */
    private function indexAdd(int $id): void {

        $mc  = $this->mc();
        $key = $this->indexKey();

        for ($i = 0; $i < 5; $i++) {
            $cas  = null;
            $list = $mc->get($key, null, $cas);

            if ($list === false) {
                if ($mc->add($key, [$id], self::TTL)) {
                    return;
                }
                continue;
            }

            if ( ! is_array($list)) {
                $list = [];
            }

            if (in_array($id, $list, true)) {
                return;
            }

            $list[] = $id;

            if ($mc->cas($cas, $key, $list, self::TTL)) {
                return;
            }
        }
    }


    /**
     * @param int $id
     * @return void
     */
    private function indexRemove(int $id): void {

        $mc  = $this->mc();
        $key = $this->indexKey();

        for ($i = 0; $i < 5; $i++) {
            $cas  = null;
            $list = $mc->get($key, null, $cas);

            if ($list === false || ! is_array($list)) {
                return;
            }

            $pos = array_search($id, $list, true);

            if ($pos === false) {
                return;
            }

            array_splice($list, $pos, 1);

            if ($mc->cas($cas, $key, $list, self::TTL)) {
                return;
            }
        }
    }
}
