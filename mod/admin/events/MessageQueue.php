<?php

namespace Core2;

/**
 * Class UnreadMessages
 * Проверяет наличие фактов, которые нужно отправить в браузер через SSE
 * @package Core2\MessageQueue
 */
class MessageQueue extends \Common implements Event {

    private $queue;
    private $_messages = [];

    const TEXT = 1;

    /**
     * @return array|string
     */
    public function dispatch() {

        return $this->_messages;
    }


    /**
     * @return bool
     */
    public function check(): bool {

        if (!$this->queue) return false;

//        $queue_status = msg_stat_queue($this->queue); //статистика очереди
        $hasMessages = true;
        $from_queue = [];
        while ($hasMessages) {
            //забираем сообщения с типом 1
            //TODO придумать разные типы сообщений
            msg_receive($this->queue, self::TEXT, $message_type, 10000, $message, false, MSG_IPC_NOWAIT | MSG_NOERROR, $error);
            if ($message) {
                $message = json_decode($message, true);
                if (!in_array($message, $from_queue)) $from_queue[key($message)][] = current($message);
            } else $hasMessages = false;
//            usleep(1000);
        }
        if ($from_queue) {
            $this->_messages = $from_queue;
            return true;
        }

        return false;
    }

    public function setQueue(\SysvMessageQueue|false $q)
    {
        if ($q) $this->queue = $q;
    }
}