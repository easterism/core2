<?php
namespace Core2;

/**
 *
 */
class Parallel extends Db {

    private array  $tasks        = [];
    private int    $pool_size    = 4;
    private bool   $print_buffer = false;
    private int    $task_id      = 1;
    private string $boundary     = '';


    /**
     * @param array|null $options
     * @throws \Zend_Exception
     */
    public function __construct(array $options = null) {
        parent::__construct();

        if ( ! empty($options['pool_size'])) {
            $this->pool_size = (int)$options['pool_size'];
        }

        if ( ! empty($options['print_buffer'])) {
            $this->print_buffer = (bool)$options['print_buffer'];
        }
    }


    /**
     * Добавление задачи
     * @param \Closure $task
     * @return int уникальный порядковый номер задачи
     * @throws \Exception
     */
    public function addTask(\Closure $task): int {

        $this->tasks[$this->task_id] = $task;

        return $this->task_id++;
    }


    /**
     * Запуск выполнения добавленных задач
     * @param \Closure|null $task_callback
     * @return array
     * @throws \Exception
     */
    public function start(\Closure $task_callback = null): array {

        $this->db->closeConnection();

        if ($this->cache->getAdapterName() !== 'Filesystem') {
            $reg = Registry::getInstance();
            $reg->set('cache', null);
        }

        $process_count = 0;
        $tasks_result  = [];
        $tasks_pid     = [];

        $this->boundary = md5(uniqid('', true));

        [$socket_parent, $socket_child] = $this->createSocketsPair();

        foreach ($this->tasks as $task_id => $task) {

            if ($process_count >= $this->pool_size) {
                if ($responses = $this->waitResponses($socket_child)) {

                    foreach ($responses as $response) {
                        if ($this->print_buffer) {
                            echo $response['buffer'];
                        }

                        $tasks_result[$response['id']] = [
                            'buffer' => $response['buffer'],
                            'result' => $response['result']
                        ];

                        if ($task_callback instanceof \Closure) {
                            $task_callback($response);
                        }

                        if ( ! empty($tasks_pid[$response['pid']])) {
                            unset($tasks_pid[$response['pid']]);
                        }

                        if ( ! empty($this->tasks[$response['id']])) {
                            unset($this->tasks[$response['id']]);
                        }
                    }

                    $process_count -= count($responses);
                }
            }
            $this->db->closeConnection();

            if ($this->cache->getAdapterName() !== 'Filesystem') {
                $reg = Registry::getInstance();
                $reg->set('cache', null);
            }
            $pid = $this->startTask($task_id, $task, $socket_child, $socket_parent);
            $tasks_pid[$pid] = $pid;

            $process_count++;
        }

        while (count($tasks_pid)) {
            if ($responses = $this->waitResponses($socket_child)) {

                foreach ($responses as $response) {
                    if ($this->print_buffer) {
                        echo $response['buffer'];
                    }

                    $tasks_result[$response['id']] = [
                        'buffer' => $response['buffer'],
                        'result' => $response['result']
                    ];

                    if ($task_callback instanceof \Closure) {
                        $task_callback($response);
                    }

                    if ( ! empty($tasks_pid[$response['pid']])) {
                        unset($tasks_pid[$response['pid']]);
                    };
                }
            }
        }

        pcntl_wait($status);

        socket_close($socket_child);
        socket_close($socket_parent);

        $this->tasks = [];

        //$this->db;

        return $tasks_result;
    }


    /**
     * @param \Socket $socket
     * @return array
     * @throws \Exception
     */
    private function waitResponses(\Socket $socket): array {

        $responses    = $this->getResponses($socket);
        $task_results = [];

        foreach ($responses as $response) {

            if ( ! empty($response['id'])) {
                $task_results[] = [
                    'id'     => $response['id'],
                    'pid'    => $response['pid'],
                    'buffer' => $response['buffer'],
                    'result' => $response['result'],
                ];

                pcntl_waitpid($response['pid'], $status);

                unset($this->tasks[$response['id']]);
            }
        }

        return $task_results;
    }


    /**
     * Запуск выполнения задачи
     * @param int      $task_id
     * @param \Closure $task
     * @param \Socket  $socket_child
     * @param \Socket  $socket_parent
     * @return int
     * @throws \Exception
     */
    private function startTask(int $task_id, \Closure $task, \Socket $socket_child, \Socket $socket_parent): int {

        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new \Exception($this->_(sprintf(
                'Не удалось породить дочерний процесс: %s', pcntl_strerror(pcntl_get_last_error())
            )));

        // Дочерний процесс
        } elseif ( ! $pid) {
            ob_start();
            socket_close($socket_child);

            // Самостоятельное завершение процесса перед выходом, иначе процесс будет закрыт вместе с родителем,
            register_shutdown_function(function () use ($task_id, $socket_parent) {
                $buffer = ob_get_clean();

                $error = error_get_last();
                if ($error &&
                    in_array($error['type'], [
                        E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR,
                        E_CORE_WARNING, E_COMPILE_WARNING, E_PARSE
                    ])
                ) {
                    $this->sendSocketData($socket_parent, [
                        'id'     => $task_id,
                        'pid'    => posix_getpid(),
                        'buffer' => (string)$buffer,
                        'result' => null,
                    ]);
                }
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

            $this->sendSocketData($socket_parent, [
                'id'     => $task_id,
                'pid'    => posix_getpid(),
                'buffer' => (string)ob_get_clean(),
                'result' => $result_value,
            ]);

            // Завершение дочернего процесса
            exit();
        }

        return $pid;
    }


    /**
     * Создание парного соединения для общения между процессами
     * @return array
     * @throws \Exception
     */
    private function createSocketsPair(): array {

        $domain       = strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? AF_INET : AF_UNIX;
        $sockets_pair = [];

        if ( ! socket_create_pair($domain, SOCK_STREAM, 0, $sockets_pair)) {
            throw new \Exception("Fail start socket_create_pair. Reason: " . socket_strerror(socket_last_error()));
        }

        return $sockets_pair;
    }


    /**
     * Отправка данных в сокет процесса
     * @param \Socket $socket
     * @param mixed   $data
     * @return void
     */
    private function sendSocketData(\Socket $socket, mixed $data): void {

        $sending_data  = serialize($data) . "--{$this->boundary}--\0\r\n";
        $buffer_length = mb_strlen($sending_data, '8bit');

        socket_write($socket, $sending_data, $buffer_length);
        socket_close($socket);
    }


    /**
     * Отправка данных в сокет процесса
     * @param \Socket $socket
     * @return array
     */
    private function getResponses(\Socket $socket): array {

        $socket_result = "";

        while ($response = socket_read($socket, 102400)) {
            $socket_result .= $response;

            if (str_ends_with($socket_result, "--{$this->boundary}--\0\r\n")) {
                break;
            }
        }


        $results     = [];
        $results_raw = explode("--{$this->boundary}--\0\r\n", $socket_result);

        foreach ($results_raw as $result_raw) {
            if ( ! empty($result_raw)) {
                $results[] = unserialize(trim($result_raw));
            }
        }

        return $results;
    }
}