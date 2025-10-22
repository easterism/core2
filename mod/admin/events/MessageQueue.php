<?php
namespace Core2;

require_once 'core2/inc/Interfaces/Event.php';

/**
 * Class UnreadMessages
 * Проверяет наличие фактов, которые нужно отправить в браузер через SSE
 * @package Core2\MessageQueue
 */
class MessageQueue extends \Common implements Event {

    private $queue;
    private $_q;

    private $_messages = [];

    const TEXT = 1;


    public function __construct(\SysvMessageQueue|false $queue)
    {
        if ($queue) $this->queue = $queue;
        $this->_q = (msg_get_queue(ftok(__FILE__, 't') + crc32($_SERVER['SERVER_NAME'])));
        parent::__construct();
    }

    /**
     * @return array|string
     */
    public function dispatch() {
        $out = $this->_messages;
        $this->_messages = [];
        return $out;
    }


    /**
     * @return bool
     */
    public function check(): bool {

        if (!$this->_q) {
            //пробуем переподключиться к системной очереди
            $this->_q = msg_get_queue(ftok(__FILE__, 't') + crc32($_SERVER['SERVER_NAME']));
        }
        if ($this->_q) {
            msg_receive($this->_q, self::TEXT, $message_type, 10000, $message, false, MSG_IPC_NOWAIT | MSG_NOERROR, $error);
            if (trim($message)) {
                $this->_messages[] = $message;
            }
        }

        if ($this->queue) {
            //юзерная очередь
            $message = '';
//        $queue_status = msg_stat_queue($this->queue); //статистика очереди
            msg_receive($this->queue, self::TEXT, $message_type, 10000, $message, false, MSG_IPC_NOWAIT | MSG_NOERROR, $error);
            if (trim($message)) {
                $this->_messages[] = $message;
            }
        }


        return count($this->_messages) ? true : false;
    }

}