<?php


/**
 * Контекстные сообщения
 * Class Alert
 */
class Alert {

    /**
     * Возвращает сообщение об успешном выполнении
     * @param string $str
     * @return string
     */
    public static function getSuccess($str) {
        return "<div class=\"alert alert-success\">{$str}</div>";
    }


    /**
     * Распечатывает сообщение об успешном выполнении
     * @param string $str
     * @return string
     */
    public static function printSuccess($str) {
        echo self::getSuccess($str);
    }


    /**
     * Возвращает сообщение с информацией
     * @param string $str
     * @return string
     */
    public static function getInfo($str) {
        return "<div class=\"alert alert-info\">{$str}</div>";
    }



    /**
     * Распечатывает сообщение с информацией
     * @param string $str
     * @return string
     */
    public static function printInfo($str) {
        echo self::getInfo($str);
    }


    /**
     * Возвращает сообщение с предупреждением
     * @param string $str
     * @return string
     */
    public static function getWarning($str) {
        return "<div class=\"alert alert-warning\">{$str}</div>";
    }


    /**
     * Распечатывает сообщение с предупреждением
     * @param string $str
     * @return string
     */
    public static function printWarning($str) {
        echo self::getWarning($str);
    }


    /**
     * Возвращает сообщение об ошибке или опасности
     * @param string $str
     * @return string
     */
    public static function getDanger($str) {
        return "<div class=\"alert alert-danger\">{$str}</div>";
    }


    /**
     * Распечатывает сообщение об ошибке или опасности
     * @param string $str
     * @return string
     */
    public static function printDanger($str) {
        echo self::getDanger($str);
    }
}