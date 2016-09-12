<?php


/**
 * Контекстные сообщения
 * Class Alert
 */
class Alert {
    private $info = array();
    private $success = array();
    private $warning = array();
    private $danger = array();
    private $i = 0;
    private $s = 0;
    private $w = 0;
    private $d = 0;

    public function __construct() {

    }

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






    /**
     * Регистрация информационного сообщения
     * @param        $head
     * @param string $explanation
     * @param bool   $force - признак принудительного вывода
     *
     * @return $this
     */
    public function info($head, $explanation = '', $force = false) {
        if ($force) {
            echo "<div class=\"im-msg-green\">{$head}<br><span>{$explanation}</span></div>";
            return;
        }
        $this->info[] = array($head, $explanation);
        $this->i++;
        return $this;
    }

    /**
     * Регистрация сообщения об успешном выполнении чего-либо
     * @param        $head
     * @param string $explanation
     * @param bool   $force
     *
     * @return $this|void
     */
    public function success($head, $explanation = '', $force = false) {
        if ($force) {
            echo "<div class=\"im-msg-green\">{$head}<br><span>{$explanation}</span></div>";
            return;
        }
        $this->success[] = array($head, $explanation);
        $this->s++;
        return $this;
    }

    /**
     * Регистрация важного сообщения
     * @param        $head
     * @param string $explanation
     * @param bool   $force
     *
     * @return $this|void
     */
    public function warning($head, $explanation = '', $force = false) {
        if ($force) {
            echo "<div class=\"im-msg-yellow\">{$head}<br><span>{$explanation}</span></div>";
            return;
        }
        $this->warning[] = array($head, $explanation);
        $this->w++;
        return $this;
    }

    /**
     * Регистрация критического сообщения
     * @param        $head
     * @param string $explanation
     * @param bool   $force
     *
     * @return $this|void
     */
    public function danger($head, $explanation = '', $force = false) {
        if ($force) {
            echo "<div class=\"im-msg-red\">{$head}<br><span>{$explanation}</span></div>";
            return;
        }
        $this->danger[] = array($head, $explanation);
        $this->d++;
        return $this;
    }

    /**
     * Принудительная очистка накопленных сообщений
     * (пока не используется)
     */
    public function clear() {
        $this->info = array();
        $this->success = array();
        $this->warning = array();
        $this->danger = array();
        $this->i = 0;
        $this->s = 0;
        $this->w = 0;
        $this->d = 0;
    }

    /**
     * Вывод всех имеющихся сообщений
     * с учетом группировки по теме
     */
    public function draw() {
        if ($msg = $this->getMsg('info')) echo "<div class=\"im-msg-blue\">" . implode("<br>", $msg) . "</div>";
        if ($msg = $this->getMsg('success')) echo "<div class=\"im-msg-green\">" . implode("<br>", $msg) . "</div>";
        if ($msg = $this->getMsg('warning')) echo "<div class=\"im-msg-yellow\">" . implode("<br>", $msg) . "</div>";
        if ($msg = $this->getMsg('danger')) echo "<div class=\"im-msg-red\">" . implode("<br>", $msg) . "</div>";
    }

    /**
     * Группировка сообщений по теме
     * @param $kind
     *
     * @return array
     */
    private function getMsg($kind) {
        $msg = array();
        foreach ($this->$kind as $k => $item) {
            if (!isset($msg[$item[0]])) {
                $msg[$item[0]] = "{$item[0]}<br><span>{$item[1]}</span>";
            } else {
                $msg[$item[0]] .= "<br><span>{$item[1]}</span>";
            }
            unset($this->$kind[$k]);
        }
        return $msg;
    }
}