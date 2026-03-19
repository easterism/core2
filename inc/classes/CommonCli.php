<?php

namespace Core2;
require_once DOC_ROOT . 'core2/inc/classes/Common.php';

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

class CommonCli extends \Common
{
    /**
     * возвращает объект времени предыдущего успешного вызова текущей команды
     *
     * @return DateTimeImmutable
     * @throws \Exception
     */
    public function getLastStart(): DateTimeImmutable
    {
        $key = array_search('-x', $_SERVER['argv']);
        $last_time = null;
        if (!empty($_SERVER['argv'][$key + 1])) {

            //$s = (new DateTime())->format('Z');
            $last_time = DateTimeImmutable::createFromFormat('U', $_SERVER['argv'][$key + 1]);
            $last_time = $last_time->setTimezone(new DateTimeZone((new DateTime())->format('e')));
        };
        return $last_time;
    }
}