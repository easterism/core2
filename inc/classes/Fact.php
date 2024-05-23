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
    private $shm_public;

    private $messages = ['global' => []];

    private $topic = 'global';

    const TEXT = 1;

    public function __construct()
    {
        $eventFile = __DIR__ . "/../../mod/admin/events/MessageQueue.php";
        $auth = Registry::get('auth');
        $user_key = $auth->LIVEID;
        if (!$user_key) $user_key = $auth->ID;
//        if (!$user_key) $user_key = -1;
        $this->shm_id     = ftok($eventFile, 't') + crc32($_SERVER['SERVER_NAME'] . strval($user_key)); //у аждого юзера своя очередь
    }

    public function __get($v)
    {
        if (!isset($this->messages[$v])) $this->messages[$v] = [];
        $this->topic = $v;
        return $this;
    }

    /**
     * @param mixed $text
     * @param bool  $is_public
     * @return void
     */
    public function message(mixed $text, bool $is_public = false): void {

        if (is_object($text)) $text = serialize($text);

        //каждый раз получаем id очереди заново, потому что она может быть очищена
        $q = !$is_public ? msg_get_queue($this->shm_id) : msg_get_queue($this->shm_public);

        if (! msg_send($q, self::TEXT, json_encode([$this->topic => $text]), false, true, $msg_err)) {
            $this->log->error($msg_err);
        };

        if (! in_array($text, $this->messages[$this->topic])) {
            $this->messages[$this->topic][] = $text;
        }

        $this->topic = 'global';
    }


    /**
     * Установка текста в указанный селектор на странице
     * @param string $selector
     * @param string $text
     * @param bool   $is_public
     * @return void
     */
    public function elementText(string $selector, string $text, bool $is_public = false): void {

        $text = json_encode(['element' => ['selector' => $selector, 'text' => $text]]);
        $q    = ! $is_public ? msg_get_queue($this->shm_id) : msg_get_queue($this->shm_public);

        if ( ! msg_send($q, self::TEXT, json_encode([$this->topic => $text]), false, true, $msg_err)) {
            $this->log->error($msg_err);
        };

        if ( ! in_array($text, $this->messages[$this->topic])) {
            $this->messages[$this->topic][] = $text;
        }

        $this->topic = 'global';
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