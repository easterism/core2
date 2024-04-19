<?php
namespace Core2;

/**
 * Class SSE
 * @package Core2
 */
class SSE extends \Common {

    private $_events = [];

    public function __construct()
    {
        parent::__construct();

        //события ядра
        $eventFile = __DIR__ . "/../../mod/admin/events/MessageQueue.php";
        require_once $eventFile;
        $shm_key = ftok($eventFile, 't') + $this->auth->ID; //у аждого юзера своя очередь
        if ($q = msg_get_queue($shm_key)) msg_remove_queue($q); //очищаем очередь при запуске SSE
        $eventClass = new MessageQueue();
        $eventClass->setQueue(msg_get_queue($shm_key));
        $this->_events["Core2-Fact"] = $eventClass;

        //события модулей
        $mods = $this->db->fetchAll($this->dataModules->select()->where("visible = 'Y'"));
        foreach ($mods as $mod) {
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
        $this->db->closeConnection();
        set_time_limit(0);
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
        } while ($flushed);

        @ob_flush();
        flush();
    }


    public function loop() {

        //модуль должен иметь папку events
        //в папке events каждый клас должен иметь namespace Core2\Mod\<Module_id>
        //в папке events каждый клас должен реализовать нетерфейс Event
        $data = [];
        foreach ($this->_events as $path => $event) {
            if ($event->check()) {
                //TODO реализовать не блокирующий вызов
                $path = str_replace("\\", "-" , $path);

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
                            if ($topic !== 'public') $topic = "-{$topic}";
                            else $topic = '';

                            echo "event: modules\n",
                            'data: ', json_encode([$path . $topic => $msg]), "\n\n";
                            $this->doFlush();
                        }
                    }
                }
            }
        }

        if ($data) {
            echo "event: Core2\n",
                'data: ' . json_encode(["done" => array_keys($data)]),
                "\n\n";
        }

        $this->doFlush();
    }
}