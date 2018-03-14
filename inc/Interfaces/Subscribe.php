<?php
/**
 * Определяет возможность подписки на события, возникающие в других модулях
 *
 * User: StepovichPE
 * Date: 21.02.2018
 * Time: 14:41
 */

interface Subscribe {

    /**
     * Будет выполнено модулем, который является источником события
     *
     * @param $module_id - идентификатор модуля, инициировавшего событие
     * @param $event - код события
     * @param $data - данные события
     * @return void,mixed
     */
    public function listen($module_id, $event, $data);
}