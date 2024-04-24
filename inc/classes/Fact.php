<?php
/**
 * регистрация фактов, о которых сообщают участники системы
 * факт в json размещается в очереди очереди сообщений System V
 */

namespace Core2;

/**
 * Class Fact
 * @package Core2
 */
class Fact {

    /**
     * @var id for System V IPC
     */
    private $shm_id;

    private $messages = ['public' => []];

    private $topic = 'public';

    const TEXT = 1;

    public function __construct()
    {
        $eventFile = __DIR__ . "/../../mod/admin/events/MessageQueue.php";
        $auth = Registry::get('auth');
        $user_key = $auth->LIVEID;
        if (!$user_key) $user_key = $auth->ID;
//        if (!$user_key) $user_key = -1;
        $this->shm_id = ftok($eventFile, 't') + crc32($user_key); //у каждого юзера своя очередь
    }

    public function __get($v)
    {
        if (!isset($this->messages[$v])) $this->messages[$v] = [];
        $this->topic = $v;
        return $this;
    }

    /**
     * @param mixed $text
     * @return void
     */
    public function message(mixed $text): void {

        if (is_object($text)) $text = serialize($text);

        //каждый раз получаем id очереди заново, потому что она может быть очищена
        $q = msg_get_queue($this->shm_id);

        if (! msg_send($q, self::TEXT, json_encode([$this->topic => $text]), false, true, $msg_err)) {
            $this->log->error($msg_err);
        };

        if (! in_array($text, $this->messages[$this->topic])) {
            $this->messages[$this->topic][] = $text;
        }

        $this->topic = 'public';
    }

    /**
     * получаем список всех фактов, которые были добавлены в этом сеансе
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * проверяет, был ли факт добавлен ранее в этом сеансе
     * @param mixed $text
     * @return bool
     */
    public function hasMessage(mixed $text): bool
    {
        if (in_array($text, $this->messages[$this->topic])) return true;
        return false;
    }


}