<?php


/**
 * Контекстные сообщения
 * Class Alert
 */
class Alert {

    protected static $in_session = false;


    /**
     * Alert constructor.
     * @param bool $in_session
     */
    public function __construct($in_session = false) {
        self::$in_session = (bool)$in_session;
    }


    /**
     * DEPRECATED
     * Возвращает сообщение об успешном выполнении
     * @param string $message
     * @return string
     */
    public static function getSuccess($message) {
        return self::create('success', $message);
    }


    /**
     * DEPRECATED
     * Распечатывает сообщение об успешном выполнении
     * @param string $str
     * @return string
     */
    public static function printSuccess($str) {
        echo self::getSuccess($str);
    }


    /**
     * DEPRECATED
     * Возвращает сообщение с информацией
     * @param string $message
     * @return string
     */
    public static function getInfo($message) {
        return self::create('info', $message);
    }



    /**
     * DEPRECATED
     * Распечатывает сообщение с информацией
     * @param string $str
     * @return string
     */
    public static function printInfo($str) {
        echo self::getInfo($str);
    }


    /**
     * DEPRECATED
     * Возвращает сообщение с предупреждением
     * @param string $message
     * @return string
     */
    public static function getWarning($message) {
        return self::create('warning', $message);
    }


    /**
     * DEPRECATED
     * Распечатывает сообщение с предупреждением
     * @param string $str
     * @return string
     */
    public static function printWarning($str) {
        echo self::getWarning($str);
    }


    /**
     * DEPRECATED
     * Возвращает сообщение об ошибке или опасности
     * @param string $message
     * @return string
     */
    public static function getDanger($message) {
        return self::create('danger', $message);
    }


    /**
     * DEPRECATED
     * Распечатывает сообщение об ошибке или опасности
     * @param string $str
     * @return string
     */
    public static function printDanger($str) {
        echo self::getDanger($str);
    }


    /**
     * Returns a message
     * @param string $type
     * @param string $header
     * @param string $message
     * @return string
     */
    public static function create($type, $message, $header = '') {

        $header_str    = $header ? "<h4>{$header}</h4>" : '';
        $alert_message = "<div class=\"alert alert-{$type}\">{$header_str}{$message}</div>";

        if (self::$in_session && isset($_SESSION)) {
            session_status();
            $_SESSION['alert_messages'][] = [$type, $alert_message];
        }

        self::$in_session = false;
        return $alert_message;
    }


    /**
     * Распечатывает сообщение об успешном выполнении
     * @param string $message
     * @param string $header
     * @return string
     */
    public static function success($message, $header = '') {
        return self::create('success', $message, $header);
    }


    /**
     * Распечатывает сообщение с информацией
     * @param string $message
     * @param string $header
     * @return string
     */
    public static function info($message, $header = '') {
        return self::create('info', $message, $header);
    }


    /**
     * Распечатывает сообщение с предупреждением
     * @param string $header,
     * @param string $message
     * @return string
     */
    public static function warning($message, $header = '') {
        return self::create('warning', $message, $header);
    }


    /**
     * Распечатывает сообщение об ошибке или опасности
     * @param string $header,
     * @param string $message
     * @return string
     */
    public static function danger($message, $header = '') {
        return self::create('danger', $message, $header);
    }


    /**
     * Record the following message in the session
     * @return Alert
     */
    public static function session() {
        return new Alert(true);
    }


    /**
     * Receiving messages session
     * @param string $type
     * @return string
     */
    public static function get($type = '') {

        $alert_messages = array();

        if ( ! empty($_SESSION) &&
            ! empty($_SESSION['alert_messages']) &&
            is_array($_SESSION['alert_messages'])
        ) {
            foreach ($_SESSION['alert_messages'] as $key => $alert_message) {
                if ( ! empty($alert_message[0]) && is_string($alert_message[0]) &&
                    ! empty($alert_message[1]) && is_string($alert_message[1]) &&
                    (empty($type) || $type == $alert_message[0])
                ) {
                    $alert_messages[] = $alert_message[1];
                    unset($_SESSION['alert_messages'][$key]);
                }
            }
        }

        return implode('', $alert_messages);
    }
}