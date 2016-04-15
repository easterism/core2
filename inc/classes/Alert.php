<?php


/**
 * Контекстные сообщения
 * Class Alert
 */
class Alert {

    /**
     * Возвращает сообщение об успешном выполнении
     * @param string $status
     * @param string $message
     * @return string
     */
    public static function get($status, $message) {
        return "<div class=\"alert alert-{$status}\">{$message}</div>";
    }


    /**
     * Возвращает сообщение об успешном выполнении
     * @param string $message
     * @return string
     */
    public static function getSuccess($message) {
        return self::get('success', $message);
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
     * Возвращает сообщение с информацией
     * @param string $message
     * @return string
     */
    public static function getInfo($message) {
        return self::get('info', $message);
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
     * Возвращает сообщение с предупреждением
     * @param string $message
     * @return string
     */
    public static function getWarning($message) {
        return self::get('warning', $message);
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
     * Возвращает сообщение об ошибке или опасности
     * @param string $message
     * @return string
     */
    public static function getDanger($message) {
        return self::get('danger', $message);
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
}