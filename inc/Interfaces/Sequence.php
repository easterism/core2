<?php


/**
 *
 */
interface Sequence {

    /**
     * @param string $resource_name
     * @param array  $records
     * Если возвращено true, то будет считаться, что вы самостоятельно сортировали объекты
     * Если возвращено false будет считаться, что нужно применить стандартную процедуру сортировки
     */
    public function sequence(string $resource_name, array $records): bool;
}