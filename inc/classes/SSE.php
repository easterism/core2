<?php
namespace Core2;

/**
 * Class SSE
 * @package Core2
 */
class SSE extends Db {

    private $_events = [];
    private $_has_content = false;
    private $_co = 0;
    private $_counterFile = '';

    public function __construct()
    {
        parent::__construct();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $clientId = md5($ua . '|' . session_id());

        $baseDir     = sys_get_temp_dir() . '/sse_clients';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0777, true);
        }
        $this->_counterFile = $baseDir . '/' . $clientId . '.counter';
        $lockFile    = $baseDir . '/' . $clientId . '.lock';
        $this->_co = $this->incrementCounter($this->_counterFile, $lockFile);

        //события ядра
        $eventFile = __DIR__ . "/../../mod/admin/events/MessageQueue.php";
        require_once $eventFile;

        $shm_key = ftok($eventFile, 't') + crc32($_SERVER['SERVER_NAME'] . strval($clientId)); //у каждого юзера своя очередь
        if ($q = msg_get_queue($shm_key)) msg_remove_queue($q); //очищаем очередь юзера при запуске SSE
        $eventClass = new MessageQueue(msg_get_queue($shm_key));
        $this->_events["Core2-Fact"] = $eventClass;

        //события модулей
        $location      = $this->getModuleLocation('dummy'); //нужно для сохранения всех модулей в реестре
        if (!Registry::isRegistered("_modules")) {
            return;
        }
        $mods = Registry::get('_modules');
        foreach ($mods as $mod) {
            if ($mod['visible'] !== 'Y') continue;
            $location      = $this->getModuleLocation($mod['module_id']);
            if (!is_dir($location . "/events")) continue;
            foreach (new \DirectoryIterator($location . "/events") as $fileInfo) {
                if ($fileInfo->isDot()) continue;
                $eventFile = $fileInfo->getBasename('.php');
                if (!$eventFile) continue;
                require_once $fileInfo->getRealPath();
                $eventFile = "Core2\Mod\\" . ucfirst($mod['module_id']) . "\\" . $eventFile;
                $eventClass = new $eventFile();
                if ($eventClass instanceof Event) {
                    $this->_events[$eventFile] = $eventClass;
                }
            }
        }

        set_time_limit(0);
        ignore_user_abort(false);
    }

    public function run()
    {
        $core_config = Registry::get('core_config');
        $exec_limit = 100;
        $usleep_time = 500000;

        if (!empty($core_config->sse->exec_limit)) {
            $exec_limit = (int)$core_config->sse->exec_limit;
        }
        if (!empty($core_config->sse->sleep_time)) {
            $usleep_time = $core_config->sse->sleep_time * 1000000;
        }
        while ($exec_limit > 0) {
            if (connection_status() != 0){
                break;
            }
            $currentCounter = $this->readCounter($this->_counterFile);
            if ($currentCounter > $this->_co) {
                //echo "event: shutdown\n";
                break;
            }

            if (connection_aborted()) {
                break;
            }
            $this->loop();

            $sec = $usleep_time / 1000000;
            $exec_limit -= $sec;
            if ($usleep_time > 1000000) sleep((int) $sec); //usleep may not be supported
            else usleep($usleep_time);
        }
    }

    /**
     * @return void
     */
    private function doFlush()
    {
        if (!headers_sent()) {
            // Disable gzip in PHP.
            ini_set('zlib.output_compression', 0);

            // Force disable compression in a header.
            // Required for flush in some cases (Apache + mod_proxy, nginx, php-fpm).
            header('Content-Encoding: none');
        }

        // Fill-up 4 kB buffer (should be enough in most cases).
        echo str_pad('', 4 * 1024);

        // Flush all buffers.
        do {
            $flushed = @ob_end_flush();
            if ($flushed) $this->_has_content = true;
        } while ($flushed);

        @ob_flush();
        flush();
    }


    private function loop() {

        //модуль должен иметь папку events
        //в папке events каждый клас должен иметь namespace Core2\Mod\<Module_id>
        //в папке events каждый клас должен реализовать нетерфейс Event
        $data = [];

        foreach ($this->_events as $path => $event) {
            try {
                if ($event->check()) {

                    //TODO реализовать не блокирующий вызов
                    $path = str_replace("\\", "-", $path);

                    ob_start();
                    $msgs = $event->dispatch();

                    $data[$path] = ob_get_clean();

                    if ($data[$path] || ($msgs && is_array($msgs))) {
                        if ($data[$path]) {
                            echo "event: modules\n",
                            'data: ', json_encode([$path => $data[$path]]), "\n\n";
                            $this->doFlush();
                        }
                        if ($msgs) {
                            foreach ($msgs as $topic => $msg) {
                                if ($topic !== 'global') $topic = "-{$topic}";
                                else $topic = '';

                                echo "event: modules\n",
                                'data: ', json_encode([$path . $topic => $msg]), "\n\n";
                                $this->doFlush();
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                //echo $e->getMessage();
            }
        }

        if ($data) {
            echo "event: Core2\n",
                'data: ' . json_encode(["done" => array_keys($data)]),
                "\n\n";
            $this->doFlush();
        }
    }

    private function incrementCounter(string $counterFile, string $lockFile): int
    {
        $lock = fopen($lockFile, 'c');
        if (!$lock) {
            return 1;
        }
        flock($lock, LOCK_EX);

        $current = 0;
        if (is_file($counterFile)) {
            $current = (int)trim((string)file_get_contents($counterFile));
        }
        $current++;
        file_put_contents($counterFile, (string)$current, LOCK_EX);

        flock($lock, LOCK_UN);
        fclose($lock);

        return $current;
    }

    private function readCounter(string $counterFile): int
    {
        if (!is_file($counterFile)) {
            return 0;
        }
        return (int)trim((string)@file_get_contents($counterFile));
    }

}