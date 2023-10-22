<?php
namespace Core2;


/**
 * Class SSE
 * @package Core2
 */
class SSE extends \Common {

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

        //TODO сделать получение событий от всех модулей
        //модуль должен иметь папку events
        //внутри каждый клас должен реализовать нетерфейс Event

        $curDate = date(DATE_ISO8601);
        echo "event: module\n",
            'data: {"time": "' . $curDate . '"}', "\n\n";

        echo "event: core2\n",
            'data: asd asd', "\n\n";
        // Send a simple message at random intervals.

        $this->doFlush();
    }


}