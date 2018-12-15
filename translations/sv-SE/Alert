<?php


/**
 * Контекстные сообщения
 * Class Alert
 */
class Alert {

    private static $memory    = array();
    private static $in_memory = false;


    /**
     * Alert constructor.
     * @param bool $in_memory
     */
    public function __construct($in_memory = false) {
        self::$in_memory = (bool)$in_memory;
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
     */
    public static function printDanger($str) {
        echo self::getDanger($str);
    }


    /**
     * Возвращает сообщение
     * @param string $type
     * @param string $header
     * @param string $message
     * @return string
     */
    public static function create($type, $message, $header = '') {

        $header_str    = $header ? "<h4>{$header}</h4>" : '';
        $alert_message = "<div class=\"alert alert-{$type}\">{$header_str}{$message}</div>";

        if (self::$in_memory) {
            self::$memory[] = [$type, $alert_message];
        }

        self::$in_memory = false;
        return $alert_message;
    }


    /**
     * Возвращает сообщение об успешном выполнении
     * @param string $message
     * @param string $header
     * @return string
     */
    public static function success($message, $header = '') {
        return self::create('success', $message, $header);
    }


    /**
     * Возвращает сообщение с информацией
     * @param string $message
     * @param string $header
     * @return string
     */
    public static function info($message, $header = '') {
        return self::create('info', $message, $header);
    }


    /**
     * Возвращает сообщение с предупреждением
     * @param string $header,
     * @param string $message
     * @return string
     */
    public static function warning($message, $header = '') {
        return self::create('warning', $message, $header);
    }


    /**
     * Возвращает сообщение об ошибке или опасности
     * @param string $header,
     * @param string $message
     * @return string
     */
    public static function danger($message, $header = '') {
        return self::create('danger', $message, $header);
    }


    /**
     * Запись следующего сообщения в память
     * @return Alert
     */
    public static function memory() {
        return new Alert(true);
    }


    /**
     * Возвращает сообщения памяти
     * @param string $type
     * @return string
     */
    public static function get($type = '') {

        $alert_messages = array();

        if ( ! empty(self::$memory)) {
            foreach (self::$memory as $key => $alert_message) {
                if ( ! empty($alert_message[0]) && is_string($alert_message[0]) &&
                     ! empty($alert_message[1]) && is_string($alert_message[1]) &&
                     (empty($type) || $type == $alert_message[0])
                ) {
                    $alert_messages[] = $alert_message[1];
                    unset(self::$memory[$key]);
                }
            }
        }

        return implode('', $alert_messages);
    }
}