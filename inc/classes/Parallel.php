<?php
namespace Core2;

/**
 *
 */
class Parallel extends Db {

    private array $pids      = [];
    private array $sockets   = [];
    private array $tasks     = [];
    private bool  $use_db    = false;
    private int   $pool_size = 4;

    /**
     * @throws \Zend_Exception
     */
    public function __construct(array $options = null) {
        parent::__construct();

        if ( ! empty($options['pool_size'])) {
            $this->pool_size = (int)$options['pool_size'];
        }
        if ( ! empty($options['use_db'])) {
            $this->use_db = (bool)$options['use_db'];
        }
    }


    /**
     * Добавление задачи
     * @param \Closure $task
     * @return void
     * @throws \Exception
     */
    public function addTask(\Closure $task): void {

        $this->tasks[] = $task;
    }


    /**
     * Запуск выполнения добавленных задач
     * @return array
     * @throws \Exception
     */
    public function start(): array {

        if ($this->use_db) {
            $this->db->closeConnection();
        }

        $process_count = 0;
        foreach ($this->tasks as $task) {
            if ($process_count >= $this->pool_size) {
                pcntl_wait($status);
                $process_count--;
            }

            $this->startTask($task);
            $process_count++;
        }

        if ($this->use_db) {
            $this->db;
        }


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

            $result[$pid] = unserialize(trim($process_result));
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
                ob_end_clean();

                // Отправка пустого сообщения при фатале
                $error = error_get_last();
                if ($error &&
                    in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_WARNING, E_COMPILE_WARNING, E_PARSE])
                ) {
                    $this->sendSocketData($socket, null);
                }

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

            $this->sendSocketData($socket, $result_value);

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