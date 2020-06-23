<?php


/**
 * Определяет возможность подключения глобальных css скриптов из модулей
 * Interface TopCss
 */
interface TopCss {

    /**
     * Возвращает массив адресов к css скриптам
     * @return array
     */
    public function topCss();
}