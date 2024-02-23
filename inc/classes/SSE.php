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

        $mods = $this->dataModules->getModuleList();
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

        foreach ($this->_events as $class_name => $event) {
            if ($event->check()) {
                //TODO реализовать не блокирующий вызов
                ob_start();
                $event->dispatch();
                $data[str_replace("\\", "-" , $class_name)] = ob_get_clean();
            }
        }

        if ($data) {
            echo "event: modules\n",
                'data: ', json_encode($data), "\n\n";

            echo "event: Core2\n",
                'data: произошли события: ',
                implode("\ndata: ", array_keys($data)),
                "\n\n";
        }

        $this->doFlush();
    }


}