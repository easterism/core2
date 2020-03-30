<?php


/**
 * Определяет возможность подключения глобальных javascript скриптов из модулей
 * Interface TopJs
 */
interface TopJs {

    /**
     * Возвращает массив адресов к js скриптам
     * @return array
     */
    public function topJs();
}