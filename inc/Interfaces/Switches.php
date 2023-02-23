<?php
namespace Core2;


/**
 *
 */
interface Switches {

    /**
     * @param string $resource_name
     * @param string $field
     * @param string $id
     * @param string $value
     * @return bool|array
     * [ status => 'Текст ошибки' ]
     * Если возвращено true, то будет считаться, что вы самостоятельно переключили нужный объект
     * Если возвращено false будет считаться, что нужно применить стандартную процедуру переключения
     */
    public function action_switch(string $resource_name, string $field, string $id, string $value);
}