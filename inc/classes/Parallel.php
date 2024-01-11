<?php
namespace Core2;

/**
 *
 */
class Parallel extends Db {

    private array $pids      = [];
    private array $sockets   = [];
    private array $tasks     = [];
    private int   $pool_size = 4;

    private static int $number = 1;


    /**
     * @throws \Zend_Exception
     */
    public function __construct(array $options = null) {
        parent::__construct();

        if ( ! empty($options['pool_size'])) {
            $this->pool_size = (int)$options['pool_size'];
        }
    }


    /**
     * Добавление задачи
     * @param \Closure $task
     * @return int уникальный порядковый номер задачи
     * @throws \Exception
     */
    public function addTask(\Closure $task): int {

        $this->tasks[self::$number] = $task;

        return self::$number++;
    }


    /**
     * Запуск выполнения добавленных задач
     * @return array
     * @throws \Exception
     */
    public function start(): array {

        $this->db->closeConnection();

        if ($this->cache->getAdapterName() !== 'Filesystem') {
            $reg = \Zend_Registry::getInstance();
            $reg->set('cache', null);
        }

        $task_numbers  = [];
        $process_count = 0;

        foreach ($this->tasks as $number => $task) {
            if ($process_count >= $this->pool_size) {
                pcntl_wait($status);
                $process_count--;
            }

            $pid = $this->startTask($task);

            $task_numbers[$pid] = $number;
            $process_count++;
        }

        $this->db;


        $result = [];
        pcntl_wait($status);

        foreach ($this->pids as $index => $pid) {
            pcntl_waitpid($pid, $status);
            $process_result = "";
            $socket         = $this->sockets[$index][1];

            while ($resp = socket_read($socket, 102400)) {
                $process_result .= $resp;

                // prevent socket_read hangs
                if ($process_result[-1] === "\n" &&
                    $process_result[-2] === "\r" &&
                    $process_result[-3] === "\0"
                ) {
                    break;
                }
            }

            socket_close($socket);

            $number = $task_numbers[$pid];
            $result[$number] = unserialize(trim($process_result));
        }

        $this->pids    = [];
        $this->sockets = [];
        $this->tasks   = [];

        return $result;
    }


    /**
     * Запуск выполнения задачи
     * @param \Closure $task
     * @return int
     * @throws \Exception
     */
    private function startTask(\Closure $task): int {

        $domain      = strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? AF_INET : AF_UNIX;
        $socket_nmbr = count($this->sockets);

        if ( ! socket_create_pair($domain, SOCK_STREAM, 0, $this->sockets[$socket_nmbr])) {
            throw new \Exception("socket_create_pair failed. Reason: " . socket_strerror(socket_last_error()));
        }

        $socket = $this->sockets[$socket_nmbr][0];
        $pid    = pcntl_fork();

        if ($pid == -1) {
            throw new \Exception($this->_(sprintf(
                'Не удалось породить дочерний процесс: %s', pcntl_strerror(pcntl_get_last_error())
            )));

        } elseif ($pid) {
            // Родительский процесс
            $this->pids[$socket_nmbr] = $pid;

        } else {
            // Дочерний процесс
            ob_start();


            // Самостоятельное завершение процесса перед выходом, иначе процесс будет закрыт вместе с родителем,
            register_shutdown_function(function () use ($socket) {
                $buffer = ob_get_clean();

                // Отправка пустого сообщения при фатале
                $error = error_get_last();
                if ($error &&
                    in_array($error['type'], [
                        E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR,
                        E_CORE_WARNING, E_COMPILE_WARNING, E_PARSE
                    ])
                ) {
                    $this->sendSocketData($socket, [
                        'result' => null,
                        'buffer' => (string)$buffer
                    ]);
                }

                $this->db->closeConnection();

                // Убивает текущий процесс без выполнения деструкторов
                posix_kill(getmypid(), SIGTERM);
            });


            try {
                $result_value = $task();
            } catch (\Exception $e) {
                $result_value = [
                    'error_message' => $e->getMessage(),
                    'file'          => $e->getFile(),
                    'line'          => $e->getLine(),
                    'trace'         => $e->getTraceAsString(),
                ];
            }

            $this->sendSocketData($socket, [
                'result' => $result_value,
                'buffer' => (string)ob_get_clean()
            ]);

            // Завершение дочернего процесса
            exit();
        }

        return $pid;
    }


    /**
     * Отправка данных в сокет процесса
     * @param \Socket $socket
     * @param mixed   $data
     * @return void
     */
    private function sendSocketData(\Socket $socket, mixed $data): void {

        // Declare $sendingData end of line , prevent socket_read hangs
        $sending_data  = serialize($data) . "\0\r\n";
        $buffer_length = mb_strlen($sending_data, '8bit');

        // If not declare $bufferLength, it is silently truncated to the length of SO_SNDBUF
        // @see https://www.php.net/manual/en/function.socket-write.php
        // @see https://www.php.net/manual/en/function.socket-get-option.php
        socket_write($socket, $sending_data, $buffer_length);
        socket_close($socket);
    }
}