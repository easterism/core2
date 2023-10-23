<?php

namespace Core2;
/**
 * Определяет возможность подписки на события, возникающие в других модулях
 *
 */
interface Event
{

    /**
     * Проверка, требуется ли генерировать событие
     *
     * @return bool
     */
    public function check() : bool;

    /**
     * Инициация события
     *
     * @return void
     */
    public function dispatch() : void;
}